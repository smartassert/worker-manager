<?php

namespace App\Enum;

enum ActionFailureType: string
{
    case UNKNOWN = 'unknown';
    case UNSUPPORTED_PROVIDER = 'unsupported_provider';
    case VENDOR_REQUEST_LIMIT_EXCEEDED = 'vendor_request_limit_exceeded';
    case VENDOR_AUTHENTICATION_FAILURE = 'vendor_authentication_failure';
    case CURL_ERROR = 'http_transport_error';
    case HTTP_ERROR = 'http_application_error';
    case UNPROCESSABLE_REQUEST = 'unprocessable_request';
    case UNKNOWN_MACHINE_PROVIDER_ERROR = 'unknown_machine_provider)error';
}
