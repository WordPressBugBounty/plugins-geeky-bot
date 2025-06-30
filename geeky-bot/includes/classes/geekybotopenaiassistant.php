<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GEEKYBOTgeekybotopenaiassistant {

    private $assistant_id_option = 'geekybot_assistant_id';
    private $vector_store_id_option = 'geekybot_vector_store_id';
    private $file_ids_option = 'geekybot_file_ids';

    function __construct() {
        // Initialize if needed
    }

    public function geekybot_exportAndPrepareData() {
        // Get base directory and assistant export folder
        $maindir = wp_upload_dir();
        $basedir = $maindir['basedir'];
        $datadirectory = GEEKYBOTincluder::GEEKYBOT_getModel('configuration')->getConfigValue('data_directory');

        $path = trailingslashit($basedir . '/' . $datadirectory);

        // Ensure /data_directory exists
        if (!file_exists($path)) {
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path);
        }

        // Ensure /assistant subdirectory exists
        $path .= 'assistant';
        if (!file_exists($path)) {
            GEEKYBOTincluder::GEEKYBOT_getModel('geekybot')->makeDir($path);
        }

        // Define file paths
        $stories_path = $path . '/geekybot_stories.json';
        $posts_path   = $path . '/geekybot_posts.json';

        // Get selected types from options
        $types = get_option('openai_assistant_upload_types', []);

        // Track files to upload
        $files_to_upload = [];

        if(in_array('openaiassistant', geekybot::$_active_addons)) {
            // Export stories
            if (in_array('story', $types)) {
                $stories_data = GEEKYBOTincluder::GEEKYBOT_getModel('openaiassistant')->geekybotExportStoryToJSON();
                if (!empty($stories_data)) {
                    file_put_contents($stories_path, json_encode($stories_data, JSON_PRETTY_PRINT));
                    $files_to_upload[] = $stories_path;
                }
            }

            // Export posts
            if (in_array('post', $types)) {
                $posts_data = GEEKYBOTincluder::GEEKYBOT_getModel('openaiassistant')->geekybotExportPostsToJSON();
                if ($posts_data == 1) {
                    GEEKYBOTMessages::GEEKYBOT_setLayoutMessage(__('Please enable AI Web Search before uploading web search data.', 'geeky-bot'), 'error','admin_openai');
                    return;
                } elseif (!empty($posts_data)) {
                    file_put_contents($posts_path, json_encode($posts_data, JSON_PRETTY_PRINT));
                    $files_to_upload[] = $posts_path;
                }
            }
        }

        // Reset OpenAI-related options
        update_option($this->vector_store_id_option, '');
        update_option($this->file_ids_option, '');

        // Call the new training function
        return $this->geekybot_trainAssistant($files_to_upload);
    }

    public function geekybot_trainAssistant($file_paths = []) {
        $uploadDir = wp_upload_dir();
        if (!file_exists($uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php')) {
            return false;
        }

        require_once $uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php';

        $apiKey = geekybot::$_configuration['geekybot_openai_api_key'];
        $model = geekybot::$_configuration['geekybot_openai_model'];
        if (empty($model)) {
            // $model = 'gpt-4-turbo';
            $model = 'gpt-4.1-mini';
        }
        $temperature = isset(geekybot::$_configuration['geekybot_openai_temperature']) ? 
        floatval(geekybot::$_configuration['geekybot_openai_temperature']) : 0.7;
        $maxTokens = isset(geekybot::$_configuration['geekybot_openai_max_tokens']) ? 
        intval(geekybot::$_configuration['geekybot_openai_max_tokens']) : null;
        
        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 30
        ]);

        try {
            // Check for existing assistant
            $existingAssistantId = get_option($this->assistant_id_option);
            $existingVectorStoreId = get_option($this->vector_store_id_option);
            $existingFileIds = get_option($this->file_ids_option) ?: [];

            // Determine if we're working with files in this request
            $hasFiles = !empty($file_paths);
            $keepExistingFiles = !empty($existingFileIds) && empty($file_paths);

            // If no files are provided in this request, clear previous file associations
            if (!$hasFiles && $keepExistingFiles) {
                // We're keeping existing files (no change)
            } elseif (!$hasFiles && !empty($existingFileIds)) {
                // No files provided now and we have existing files - clear them
                $existingFileIds = [];
                $existingVectorStoreId = null;
                delete_option($this->vector_store_id_option);
                delete_option($this->file_ids_option);
                $keepExistingFiles = false;
            }

            // Verify vector store exists if we're keeping existing files
            if ($existingVectorStoreId && $keepExistingFiles) {
                try {
                    $client->get("vector_stores/$existingVectorStoreId");
                } catch (RequestException $e) {
                    // Vector store doesn't exist or is invalid
                    $existingVectorStoreId = null;
                    $existingFileIds = [];
                    delete_option($this->vector_store_id_option);
                    delete_option($this->file_ids_option);
                    $keepExistingFiles = false;
                    $hasFiles = !empty($file_paths); // Check if we have new files to upload
                }
            }

            // If we have file paths but no existing IDs, upload files
            if ($hasFiles && (empty($existingFileIds) || empty($existingVectorStoreId))) {
                $uploadedFileIds = [];
                
                foreach ($file_paths as $file_path) {
                    if (!file_exists($file_path)) {
                        error_log("GeekyBot: File not found - " . $file_path);
                        continue;
                    }

                    $response = $client->post('files', [
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => fopen($file_path, 'r'),
                                'filename' => basename($file_path)
                            ],
                            [
                                'name' => 'purpose',
                                'contents' => 'assistants'
                            ]
                        ]
                    ]);

                    $fileData = json_decode($response->getBody(), true);
                    $uploadedFileIds[] = $fileData['id']; // Store each file ID
                }

                if (!empty($uploadedFileIds)) {
                    $existingFileIds = $uploadedFileIds;
                    update_option($this->file_ids_option, $existingFileIds);
                    
                    $vectorStoreResponse = $client->post('vector_stores', [
                        'json' => [
                            'name' => 'GeekyBot Knowledge Base',
                            'file_ids' => $existingFileIds, // Use all uploaded IDs
                            'expires_after' => null // Keep store active indefinitely
                        ]
                    ]);

                    $vectorStoreData = json_decode($vectorStoreResponse->getBody(), true);
                    $existingVectorStoreId = $vectorStoreData['id'];
                    update_option($this->vector_store_id_option, $existingVectorStoreId);
                    $keepExistingFiles = true;
                }
            }

            // Determine if we're using files (either new or existing)
            $usingFiles = $hasFiles || $keepExistingFiles;

            // Assistant configuration based on whether we're using files
            $assistantConfig = [
                'name' => 'GeekyBot Support',
                'model' => $model,
                'temperature' => $temperature,
                'instructions' => $usingFiles ? 
                    'You MUST follow these rules EXACTLY:
                    1. FIRST search the knowledge file for answers
                    2. If found, respond ONLY with the exact matching text
                    3. If not found, provide a helpful answer but start with: "[General Answer]"
                    4. Never mix file content with general knowledge'
                    :
                    'You are a helpful assistant. Provide clear, concise answers to user questions.'
            ];
            // Add max_tokens if set
            if (!is_null($maxTokens)) {
                // $assistantConfig['max_tokens'] = $maxTokens;
            }

            // Add file search tools only if we're using files
            if ($usingFiles) {
                $assistantConfig['tools'] = [['type' => 'file_search']];
                $assistantConfig['tool_resources'] = [
                    'file_search' => [
                        'vector_store_ids' => [$existingVectorStoreId]
                    ]
                ];
            } else {
                // When not using files, don't include tool_resources at all
                $assistantConfig['tools'] = [];
                unset($assistantConfig['tool_resources']); // Remove the key completely
            }

            // Use existing or create new assistant
            if (empty($existingAssistantId)) {
                $assistantResponse = $client->post('assistants', [
                    'json' => $assistantConfig
                ]);

                $assistantData = json_decode($assistantResponse->getBody(), true);
                $existingAssistantId = $assistantData['id'];
                update_option($this->assistant_id_option, $existingAssistantId);
            } else {
                // Update existing assistant with current configuration
                $assistantResponse = $client->post("assistants/$existingAssistantId", [
                    'json' => $assistantConfig
                ]);
            }

            if (!empty($file_paths)) {
                delete_option('geekyboot_changed_posts');
            }
            return true;

        } catch (RequestException $e) {
            error_log("GeekyBot API Error: " . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function geekybot_queryAssistant($query) {
        $uploadDir = wp_upload_dir();
        if (!file_exists($uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php')) {
            return false;
        }

        require_once $uploadDir['basedir'] . '/geekybotLibraries/openAI/geekybot_openai_assistant_client_library-main/autoload.php';

        $apiKey = geekybot::$_configuration['geekybot_openai_api_key'];
        $model = geekybot::$_configuration['geekybot_openai_model'];
        $temperature = isset(geekybot::$_configuration['geekybot_openai_temperature']) ? 
        floatval(geekybot::$_configuration['geekybot_openai_temperature']) : 0.7;
        $maxTokens = isset(geekybot::$_configuration['geekybot_openai_max_tokens']) ? 
        intval(geekybot::$_configuration['geekybot_openai_max_tokens']) : null;
        if (empty($model)) {
            // $model = 'gpt-4-turbo';
            $model = 'gpt-4.1-mini';
        }
        
        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ],
            'timeout' => 30
        ]);

        try {
            $existingAssistantId = get_option($this->assistant_id_option);
            $existingVectorStoreId = get_option($this->vector_store_id_option);
            $existingFileIds = get_option($this->file_ids_option) ?: [];

            if (empty($existingAssistantId)) {
                return __('Assistant not initialized. Please train the assistant first.', 'geeky-bot');
            }

            // Determine if we're using files
            $usingFiles = !empty($existingFileIds) && !empty($existingVectorStoreId);

            // Create thread and send message
            $threadResponse = $client->post('threads');
            $threadData = json_decode($threadResponse->getBody(), true);
            $threadId = $threadData['id'];

            $client->post("threads/$threadId/messages", [
                'json' => [
                    'role' => 'user',
                    // 'content' => $query
                    'content' => "Answer precisely while being concise. Prioritize accuracy over brevity.\n\nQuestion: " . $query
                ]
            ]);

            $runConfig = [
                'assistant_id' => $existingAssistantId,
                'temperature' => $temperature
            ];

            // Add max_tokens if set
            if (!is_null($maxTokens)) {
                // $runConfig['max_tokens'] = $maxTokens;
            }

            // Add additional instructions only if we're using files
            if ($usingFiles) {
                $runConfig['additional_instructions'] = 'You MUST follow these rules EXACTLY:
                    1. FIRST search the knowledge file for answers
                    2. If found, respond ONLY with the exact matching text
                    3. If not found, provide a helpful answer but start with: "[General Answer]"
                    4. Never mix file content with general knowledge';
            } else {
                $runConfig['additional_instructions'] = 'You are a helpful assistant. Provide clear, concise answers to user questions.';
            }

            $runResponse = $client->post("threads/$threadId/runs", [
                'json' => $runConfig
            ]);
            
            $runData = json_decode($runResponse->getBody(), true);
            $runId = $runData['id'];

            // Wait for completion
            do {
                sleep(1); // Reduced sleep time for general queries
                $statusResponse = $client->get("threads/$threadId/runs/$runId");
                $statusData = json_decode($statusResponse->getBody(), true);
                $status = $statusData['status'];
            } while ($status === 'queued' || $status === 'in_progress');

            if ($status === 'completed') {
                // for debuging
                // $tokenUsage = $statusData['usage'] ?? null;
                // if ($tokenUsage) {
                //     error_log("Token usage - Prompt: {$tokenUsage['prompt_tokens']} | Completion: {$tokenUsage['completion_tokens']}");
                // }
                $messagesResponse = $client->get("threads/$threadId/messages");
                $messagesData = json_decode($messagesResponse->getBody(), true);
                
                foreach ($messagesData['data'] as $message) {
                    if ($message['role'] === 'assistant') {
                        foreach ($message['content'] as $content) {
                            if ($content['type'] === 'text') {
                                return esc_html($content['text']['value']);
                            }
                        }
                    }
                }
            }

            return __('No response from assistant.', 'geeky-bot');

        } catch (RequestException $e) {
            error_log("GeekyBot API Error: " . $e->getMessage());
            return $e->getMessage();
        }
    }
}
?>