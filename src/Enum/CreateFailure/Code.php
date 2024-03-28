<?php

namespace App\Enum\CreateFailure;

enum Code: int
{
    case UNKNOWN = 0;
    case UNSUPPORTED_PROVIDER = 1;
    case API_LIMIT_EXCEEDED = 2;
    case API_AUTHENTICATION_FAILURE = 3;
    case CURL_ERROR = 4;
    case HTTP_ERROR = 5;
    case UNPROCESSABLE_REQUEST = 6;
    case UNKNOWN_MACHINE_PROVIDER_ERROR = 7;
}
