<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotdialogflow {

    function __construct( ) {
    }

    public function geekybot_dialogflow($query, $chat_id){
        $uploadDir = wp_upload_dir();
        if (file_exists($uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php')) {
            require_once $uploadDir['basedir'] . '/geekybotLibraries/dialogFlow/geekybot_google_client-main/autoload.php';
            
            $JsonFileContents = get_option('geekybot_dialogflow_json');
            $project_ID = geekybot::$_configuration['geekybot_dialogflow_project_id'];

            $session_id = $chat_id;
            $language = "en";
            $client = new \Google_Client();
            $client->useApplicationDefaultCredentials();
            $client->setScopes (['https://www.googleapis.com/auth/dialogflow']);
            $array = json_decode($JsonFileContents, true);
            $client->setAuthConfig($array);
            
            // $query = "what is today date";

            try {
                $httpClient = $client->authorize();
                $apiUrl = "https://dialogflow.googleapis.com/v2/projects/{$project_ID}/agent/sessions/{$session_id}:detectIntent";

                $response = $httpClient->request('POST', $apiUrl, [
                    'json' => ['queryInput' => ['text' => ['text' => $query, 'languageCode' => $language]],
                        'queryParams' => ['timeZone' => '']]
                ]);
                
                $contents = $response->getBody()->getContents();

                $data = json_decode($contents, true);
                return $reply = $data['queryResult']['fulfillmentText'] ?? __('No response', 'geeky-bot');

            }catch(Exception $e) {
                return json_encode(array('error'=>$e->getMessage()));exit;
            }
        } else {
            return __('Dialogflow setup error!', 'geeky-bot');
        }
    }
    
}

?>
