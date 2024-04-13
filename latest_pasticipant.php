<?php
require_once('ripcord.php');
$url = 'http://36.92.26.59:10016/';
$db = 'POLTEKAD';
$username = 'admin2@poltekad.ac.id';
$password = '1234567';

$models = ripcord::client("$url/xmlrpc/2/object");
$common = ripcord::client("$url/xmlrpc/2/common");
$uid = $common->authenticate($db, $username, $password, array());


$servername = "localhost";
$usernameDb = "root";
$passwordDB = "";
$dbname = "survey_new";

$conn = new mysqli($servername, $usernameDb, $passwordDB, $dbname);
if ($conn->connect_error) 
{
  die("Connection failed: " . $conn->connect_error);
}
//latest participant
$latestParticipantId = $conn->query("SELECT MAX(participant_id) AS jml FROM survey")->fetch_array();

if($latestParticipantId['jml'] > 0)
{
		$surveiItemList = $models->execute_kw($db, $uid, $password
		, 'survey.user_input'
		, 'search_read', 
			array(
				array(
					array('survey_id','=',66),
					array('state','=','done'),
					array('id','>',$latestParticipantId['jml']) //lebih besar dari latest participant
				)
			)
		); 
	}else{
			$surveiItemList = $models->execute_kw($db, $uid, $password
		, 'survey.user_input'
		, 'search_read', 
			array(
				array(
					array('survey_id','=',66),
					array('state','=','done')
				)
			)
		); 
	}
//echo json_encode($surveiItemList,true);

$arrQts = [];
$total = 0;
$dataSheet = [];
//inisiasi itemlist / kursi participant
		foreach ($surveiItemList as $surveiItemListKey => $surveiItemListValue) 
		{

			$surveiItemDetail = $models->execute_kw($db, $uid, $password
				, 'survey.user_input'
				, 'search_read', 
					array(
						array(
							array('id','=',$surveiItemListValue['id']) // from {1} => object array ['id'] => under looping;
						)
					)
				);

				foreach ($surveiItemDetail[0]['predefined_question_ids'] as $surveiItemDetailValueUiLiKey => $surveiItemDetailValueUiLiValue) 
				{
					$arrQts[$surveiItemListValue['id']][$surveiItemDetailValueUiLiKey] = $surveiItemDetailValueUiLiValue;
				}
		}
//item value / jawaban dari participant
		$noK = -1; 
		foreach ($arrQts as $arrQtsKey => $arrQtsValue) 
		{
			$noK++;
			$dataSheet[$noK]['items'] = null;
			$surveiItemPerQuestion = $models->execute_kw($db, $uid, $password
			, 'survey.user_input.line'
			, 'search_read', 
				array(
					array(
						array('survey_id','=',66),
						array('user_input_id','=',$arrQtsKey),
						array('question_id','=',$arrQtsValue) // from {2} => object array ['predefined_question_ids'] => under looping
						)
					)
				);
			//break;
			//echo json_encode($surveiItemPerQuestion);
			//inisiasi filter value/jawaban participant
			$dataSheet[$noK]['item'] = [];
			$nonya = -1;
			foreach ($surveiItemPerQuestion as $surveiItemPerQuetsionKey => $surveiItemPerQuetsionValue) 
			{
				$surveyId = 66;
				$answerValue = intval($surveiItemPerQuetsionValue['display_name']);
				$answerType = $surveiItemPerQuetsionValue['answer_type'];
				$participantId = $arrQtsKey;
				$qtsId = $surveiItemPerQuetsionValue['id'];
				$createdAt = $surveiItemPerQuetsionValue['write_date'];
				$cek = "SELECT * FROM survey WHERE 
						survey_id='$surveyId' 
						AND 
						quetsion_id='$qtsId'
						AND 
						participant_id='$participantId'";
				$result = $conn->query($cek);
				if($result->num_rows <= 0) //
				{
					$nonya++;
					if(isset($surveiItemPerQuetsionValue['question_id'][1]))
					{
						$typeQuetsion = $surveiItemPerQuetsionValue['question_id'][1];
						//$typeQuetsion = explode(' ', $typeQuetsion);
						if(isset($typeQuetsion))
						{
							if(in_array($typeQuetsion, $arrNotScrore))
							{
								$answerValue = $surveiItemPerQuetsionValue['value_char_box'];
								$answerValue = str_replace("'", ' ', $answerValue);
							}elseif(in_array($typeQuetsion, $arrJk)){
								if(isset($surveiItemPerQuetsionValue['suggested_answer_id'][1]))
								{
									$answerValue = $surveiItemPerQuetsionValue['suggested_answer_id'][1];
									$answerValue = str_replace("'", ' ', $answerValue);
								}
								
								
							}elseif(in_array($typeQuetsion, $arrNum))
							{
								$answerValue = $surveiItemPerQuetsionValue['value_numerical_box'];
							}else{
								$answerValue = $surveiItemPerQuetsionValue['answer_score'];
							}
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

//melanjutkan proses input spreadshet
if($total > 0)
{
	$total = 0;
	$totalQts = 24;
	$limit = 60;
	$jml = $conn->query("SELECT COUNT(*) AS jml FROM survey WHERE spreadsheet = 1")->fetch_array();

	//echo $jml['jml']*2;

	$qry = "SELECT *  FROM survey WHERE spreadsheet = 0 GROUP BY participant_id ORDER BY id LIMIT $limit";
	$query 	= mysqli_query($conn, $qry);
	$arr = [];
	while ($row = mysqli_fetch_array($query)) 
	{
		$arr[$total] = $row['participant_id'];
		$total++;
	}
	$dataSheet = [];
	$jalan = 0;


	foreach ($arr as $key => $value) 
	{
		$qry = "SELECT *  FROM survey WHERE participant_id = $value ORDER BY id";
		$query 	= mysqli_query($conn, $qry);
		$titit = 0;
		while ($row = mysqli_fetch_array($query)) 
		{
			$dataSheet[$key]['items'][$titit] = $row['value'];
			$titit++;

			$id = $row['id'];
			$sql = "UPDATE survey SET spreadsheet= 1 WHERE id=$id";

			if ($conn->query($sql) === TRUE) {
				$jalan++;
			}
		}
	}

	$conn->close();
//penempatan jawaban dari google spreadsheet
	$jml = $jml['jml'] / 24;
	if($total > 0) //spreedsheet
	{
		if($jml < 3)
		{
			 $jml = 0;
		}
		updateSheet($dataSheet,$jml);
	}
}

echo $total;

function updateSheet($data,$jml)
	{
		require_once('vendor/autoload.php');
		$client = new \Google_Client();
		$client->setApplicationName('Google Sheets API');
		$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
		$client->setAccessType('offline');
		$path = 'survei-kepuasan-418923-618417565eea.json'; // credentials.json is the key file we downloaded while setting up our Google Sheets API
		$client->setAuthConfig($path);
		$service = new \Google_Service_Sheets($client);
		$spreadsheetId = '1w4jCWvWMlaLqKytUlheu9x6AqRsZkNex';
		$range = 'data'; // here we use the name of the Sheet to get all the rows
		$response = $service->spreadsheets_values->get($spreadsheetId, $range);
		$values = $response->getValues();
		$cell = $jml+2;
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



