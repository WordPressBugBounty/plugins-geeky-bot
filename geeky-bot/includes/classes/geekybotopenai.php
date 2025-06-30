<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotopenai {

    function __construct( ) {
        
    }

    public function geekybot_openai($query) {
        $api_key = geekybot::$_configuration['geekybot_openai_api_key'];
        $model = geekybot::$_configuration['geekybot_openai_model'];
        $max_tokens = isset(geekybot::$_configuration['geekybot_openai_max_tokens']) ? 
            intval(geekybot::$_configuration['geekybot_openai_max_tokens']) : null;
        $temperature = isset(geekybot::$_configuration['geekybot_openai_temperature']) ? 
            floatval(geekybot::$_configuration['geekybot_openai_temperature']) : 0.7;
        
        if (empty($model)) {
            $model = 'gpt-3.5-turbo';
        }

        // OpenAI API endpoint
        $url = 'https://api.openai.com/v1/chat/completions';

        // Prepare the request body
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $query]
            ],
            'temperature' => $temperature
        ];

        // Only add max_tokens if it's set (it's optional)
        if (!is_null($max_tokens)) {
            $body['max_tokens'] = $max_tokens;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ]);

        // Handle the response
        if (is_wp_error($response)) {
            return 'Request failed: ' . $response->get_error_message();
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($data['choices'][0]['message']['content'])) {
            return esc_html($data['choices'][0]['message']['content']);
        }
        
        return esc_html($data['error']['message'] ?? 'Unknown error');
    }
    
}

?>
