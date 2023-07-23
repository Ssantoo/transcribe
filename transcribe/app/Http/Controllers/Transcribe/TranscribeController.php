<?php

namespace App\Http\Controllers;


use Aws\TranscribeService\TranscribeServiceClient;
use Aws\Exception\AwsException;
use OpenAI\OpenAI;
use OpenAI\Api\Completion;

class TranscriptionController extends Controller
{
    public function transcribe()
    {

        //클라이언트 객체를 생성
        $transcribeClient  = new TranscribeServiceClient([
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'us-east-1',
        ]);

        $jobName = 'TranscribeJob';
        $audioFileUrl = 's3://mybucket/test.mp3';  // 여기에 S3 오디오 파일 URL
        $languageCode = 'kor';

        try {

            // 트랜스크립션 작업을 시작
            $result = $transcribeClient ->startTranscriptionJob([
                'TranscriptionJobName' => $jobName,
                'Media' => [
                    'MediaFileUri' => $audioFileUrl,
                ],
                'MediaFormat' => 'mp3',  //파일 형식 적용
                'LanguageCode' => $languageCode,
            ]);

            // 트랜스크립션 작업의 상태
            do {
                $result = $transcribeClient->getTranscriptionJob([
                    'TranscriptionJobName' => $jobName,
                ]);
                sleep(10);
            } while ($result['TranscriptionJob']['TranscriptionJobStatus'] == 'IN_PROGRESS');


            // 트랜스크립션 작업이 완료되면 변환된 텍스트(JSON) 파일의 URL을 가져옵니다.
            if ($result['TranscriptionJob']['TranscriptionJobStatus'] == 'COMPLETED') {
                $transcriptFileUrl = $result['TranscriptionJob']['Transcript']['TranscriptFileUri'];

                // JSON 파일을 다운로드하고 내용 읽기
                $transcriptJson = file_get_contents($transcriptFileUrl);
                $transcriptData = json_decode($transcriptJson, true);
                $transcriptText = $transcriptData['results']['transcripts'][0]['transcript'];

                // 번역을 수행
                $translatedText = $this->translateWithChatGPT($transcriptText);

                echo "번역된 자막: {$translatedText}\n";
            }
        } catch (AwsException $e) {

            echo $e->getMessage();
        }
    }

    private function translateWithChatGPT($text)
    {
        OpenAI::setApiKey('api-key');  // 여기에 OpenAI API 키

        $api = new OpenAI(['engine' => 'text-davinci-003']);
        $prompt = "Translate the following English text to Korean: \"$text\"";  // 필요에 따라 원하는 언어로 변경 가능

        $completion = $api->completions()->create(
            'chat.models:davinci',
            [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant that translates English to Korean.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]
        );

        $translatedText = $completion['choices'][0]['message']['content'];

        return $translatedText;
    }






}
