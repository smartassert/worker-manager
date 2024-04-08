<?php

namespace App\Enum;

enum ActionFailureType: string
{
    case UNKNOWN = 'unknown';
    case UNSUPPORTED_PROVIDER = 'unsupported_provider';
    case API_LIMIT_EXCEEDED = 'api_limit_exceeded';
    case API_AUTHENTICATION_FAILURE = 'api_authentication_failure';
    case CURL_ERROR = 'http_transport_error';
    case HTTP_ERROR = 'http_application_error';
    case UNPROCESSABLE_REQUEST = 'unprocessable_request';
    case UNKNOWN_MACHINE_PROVIDER_ERROR = 'unknown_machine_provider)error';
}
