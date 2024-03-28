<?php

namespace App\Enum\ActionFailure;

enum Reason: string
{
    case UNKNOWN = 'unknown';
    case UNSUPPORTED_PROVIDER = 'unsupported provider';
    case API_LIMIT_EXCEEDED = 'api limit exceeded';
    case API_AUTHENTICATION_FAILURE = 'api authentication failure';
    case CURL_ERROR = 'http transport error';
    case HTTP_ERROR = 'http application error';
    case UNPROCESSABLE_REQUEST = 'unprocessable request';
    case UNKNOWN_MACHINE_PROVIDER_ERROR = 'unknown machine provider error';
}
