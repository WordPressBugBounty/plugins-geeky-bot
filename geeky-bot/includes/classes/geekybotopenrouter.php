<?php

if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTgeekybotopenrouter {

    function __construct( ) {
    }

    public function geekybot_openrouter($query){
        $openRouterApiKey = geekybot::$_configuration['geekybot_openrouter_api_key']; // Replace with your actual key

        $model = geekybot::$_configuration['geekybot_openrouter_model'] ?? 'deepseek/deepseek-r1:free';
        // $model = 'openai/gpt-4o';

        $temperature =  isset(geekybot::$_configuration['geekybot_openrouter_temperature']) ? (float)geekybot::$_configuration['geekybot_openrouter_temperature'] : 0.7; // Default fallback value
        $max_tokens = (int)geekybot::$_configuration['geekybot_openrouter_max_tokens'] ?? 150;

        $messages = [
            ['role' => 'user', 'content' => $query],
        ];

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
        ]);

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $openRouterApiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(), // Required by OpenRouter
                'X-Title' => get_bloginfo('name'), // Required by OpenRouter
            ],
            'body' => $payload,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200) {
            return $data['error']['message'] ?? __('API request failed', 'geeky-bot');
        }

        // Handle different response structures
        $message_content = '';

        // Case 1: Standard response with content
        if (!empty($data['choices'][0]['message']['content'])) {
            $message_content = $data['choices'][0]['message']['content'];
        } 
        // Case 2: Free model refusal/reasoning pattern
        elseif (!empty($data['choices'][0]['message']['reasoning'])) {
            $message_content = $this->geekybot_format_free_model_response(
                $data['choices'][0]['message']['reasoning'],
                $data['choices'][0]['finish_reason'] ?? null
            );
        }
        // Case 3: Empty content but has refusal reason
        elseif (!empty($data['choices'][0]['message']['refusal'])) {
            $message_content = __('The model refused to answer: ', 'geeky-bot') . $data['choices'][0]['message']['refusal'];
        }
        // Case 4: No content at all
        else {
            $message_content = $this->geekybot_handle_empty_response(
                $data['choices'][0]['finish_reason'] ?? null,
                $data['model'] ?? 'unknown'
            );
        }

        return $message_content;
    }

    /**
     * Format free model's reasoning response
     */
    private function geekybot_format_free_model_response($reasoning, $finish_reason) {
        // Extract the actual answer from reasoning text
        if (preg_match('/So (?:the|its) (?:answer|capital) (?:is|would be) ([^.]+)\./i', $reasoning, $matches)) {
            return trim($matches[1]);
        }
        
        // Handle truncated responses
        if ($finish_reason === 'length') {
            return __('Partial response: ', 'geeky-bot') . $reasoning;
        }
        
        return $reasoning;
    }

    /**
     * Handle empty content responses
     */
    private function geekybot_handle_empty_response($finish_reason, $model) {
        switch ($finish_reason) {
            case 'length':
                return __('Response was truncated due to token limits', 'geeky-bot');
            case 'filter':
                return __('The response was filtered', 'geeky-bot');
            case 'null':
                return __('Model returned empty response', 'geeky-bot');
            default:
                return sprintf(
                    __('No response content from %s (finish reason: %s)', 'geeky-bot'),
                    $model,
                    $finish_reason ?? 'unknown'
                );
        }
    }
    
}

?>
