<?php

namespace App\Http\Controllers;

use Aws\TranscribeService\TranscribeServiceClient;
use Aws\Exception\AwsException;

class TranscriptionController extends Controller
{
    public function transcribe()
    {
        $client = new TranscribeServiceClient([
            'profile' => 'default',
            'version' => 'latest',
            'region' => 'us-east-1',
        ]);

        $jobName = 'TranscribeJob';
        $audioFileUrl = 's3://mybucket/myaudiofile.mp3';
        $languageCode = 'en-US';

        try {
            $result = $client->startTranscriptionJob([
                'TranscriptionJobName' => $jobName,
                'Media' => [
                    'MediaFileUri' => $audioFileUrl,
                ],
                'MediaFormat' => 'mp3',
                'LanguageCode' => $languageCode,
            ]);


            do {
                $result = $client->getTranscriptionJob([
                    'TranscriptionJobName' => $jobName,
                ]);
                sleep(10);
            } while ($result['TranscriptionJob']['TranscriptionJobStatus'] == 'IN_PROGRESS');

            if ($result['TranscriptionJob']['TranscriptionJobStatus'] == 'COMPLETED') {
                $transcriptFileUrl = $result['TranscriptionJob']['Transcript']['TranscriptFileUri'];
                echo "Transcript file URL: {$transcriptFileUrl}\n";
            }
        } catch (AwsException $e) {
            echo $e->getMessage();
        }
    }
}
