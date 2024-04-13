<?php
require_once('ripcord.php');
$url = 'http://36.92.26.59:10016/';
$db = 'POLTEKAD';
$username = 'admin2@poltekad.ac.id';
$password = '1234567';

$models = ripcord::client("$url/xmlrpc/2/object");
$common = ripcord::client("$url/xmlrpc/2/common");
$uid = $common->authenticate($db, $username, $password, array());

// connect db
$servername = "localhost";
$usernameDb = "root";
$passwordDB = "";	
$dbname = "dbsurvey";

// Create connection
$conn = new mysqli($servername, $usernameDb, $passwordDB, $dbname);
// Check connection
if ($conn->connect_error) 
{
  die("Connection failed: " . $conn->connect_error);
}

$conn->query("TRUNCATE survey"); //mengosongkan semua data di tabel survey answers
// list jawban done {1}
$surveiItemList = $models->execute_kw($db, $uid, $password
	, 'survey.user_input'
	, 'search_read', 
		array(
			array(
				array('survey_id','=',77),
				array('state','=','done')
			)
		)
	); 
//echo print_r($surveiItemList);
$arrQts = [];
$total = 0;
$dataSheet = [];
//echo print_r($surveiItemList[0]['id']);
		foreach ($surveiItemList as $surveiItemListKey => $surveiItemListValue) 
		{
			// list jawban detail {2}
			$surveiItemDetail = $models->execute_kw($db, $uid, $password
				, 'survey.user_input'
				, 'search_read', 
					array(
						array(
							array('id','=',$surveiItemListValue['id']) // from {1} => object array ['id'] => under looping;
						)
					)
				);
				//echo print_r($surveiItemDetail[0]);
				foreach ($surveiItemDetail[0]['predefined_question_ids'] as $surveiItemDetailValueUiLiKey => $surveiItemDetailValueUiLiValue) 
				{
					$arrQts[$surveiItemListValue['id']][$surveiItemDetailValueUiLiKey] = $surveiItemDetailValueUiLiValue;
				}
		}
		//echo print_r($arrQts);
		$noK = -1;
		$jml = $conn->query("SELECT COUNT(*) AS jml FROM survey GROUP BY participant_id")->fetch_array();
		foreach ($arrQts as $arrQtsKey => $arrQtsValue) 
		{
			$noK++;
			$dataSheet[$noK]['items'] = null;
			// jawaban detail per item {3}
			$surveiItemPerQuestion = $models->execute_kw($db, $uid, $password
			, 'survey.user_input.line'
			, 'search_read', 
				array(
					array(
						array('survey_id','=',77),
						array('user_input_id','=',$arrQtsKey),
						array('question_id','=',$arrQtsValue) // from {2} => object array ['predefined_question_ids'] => under looping
						)
					)
				);
			//$dataSheet[$noK]['item'] = [];
			$nonya = -1;
			foreach ($surveiItemPerQuestion as $surveiItemPerQuestionKey => $surveiItemPerQuestionValue) 
			{
				$surveyId = 77;
				$answerValue = intval($surveiItemPerQuestionValue['display_name']);
				$answerType = $surveiItemPerQuestionValue['answer_type'];
				$participantId = $arrQtsKey;
				$qtsId = $surveiItemPerQuestionValue['id'];
				$createdAt = $surveiItemPerQuestionValue['write_date'];
				//check dulu
				//echo print_r($surveiItemPerQuestionValue);
				$cek = "SELECT * FROM survey WHERE 
						survey_id='$surveyId' 
						AND 
						quetsion_id='$qtsId'
						AND 
						participant_id='$participantId'";
				$result = $conn->query($cek);
				if($result->num_rows <= 0) //filter type data matrix
				{
					if(isset($surveiItemPerQuestionValue['matrix_row_id']))
					{
						if(is_array($surveiItemPerQuestionValue['matrix_row_id']))
						{
							$nonya++;
							$answerValue = $surveiItemPerQuestionValue['suggested_answer_id'][1];
							$dataSheet[$noK]['items'][$nonya] = $answerValue;
							$sql = "INSERT INTO survey (survey_id, quetsion_id, type, value, participant_id, created_at)
									VALUES ('$surveyId'
											,'$qtsId'
											,'$answerType'
											,'$answerValue'
											,'$participantId'
											,'$createdAt')";
							if ($conn->query($sql) === TRUE) 
							{
								$total++;
							}
						}
					} 
				}
			}
		}


if($total > 0)
{
	updateSheet($dataSheet,$jml['jml']);
}
$conn->close();
echo print_r($total);

//untuk unpdate sheet (kendaraan buat ke spreadshet)
function updateSheet($data,$jml)
{
	require_once('vendor/autoload.php');
	$client = new \Google_Client();
	$client->setApplicationName('Google Sheets API');
	$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
	$client->setAccessType('offline');
	// credentials.json is the key file we downloaded while setting up our Google Sheets API
	$path = 'survey17-95d8dffb00c0.json';
	$client->setAuthConfig($path);
	// configure the Sheets Service
	$service = new \Google_Service_Sheets($client);
	$spreadsheetId = '1Tv0WwrUeyZQKBIRp_Te7r9z5Zwfa66CmK9mCcxwUHoo';
	//$spreadsheet = $service->spreadsheets->get($spreadsheetId);
	//var_dump($spreadsheet);
	$range = 'data'; // here we use the name of the Sheet to get all the rows
	$response = $service->spreadsheets_values->get($spreadsheetId, $range);
	$values = $response->getValues();
	//update data
	$cell = $jml+3; 
	foreach ($data as $dataKey => $dataValue) 
	{
		if(!is_null($dataValue['items']))
		{
			$cell++;
			$updateRow = $dataValue['items'];
			$rows = [$updateRow];
			$valueRange = new \Google_Service_Sheets_ValueRange();
			$valueRange->setValues($rows);
			$range = 'Data!A'.$cell.''; 
			$options = ['valueInputOption' => 'USER_ENTERED'];
			$service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);
		}
		
	}
}
