<?php
// app/Helpers/ApiResponse.php

namespace App\Helpers;

class ApiResponse
{
/**
* Success response
*
* @param mixed $data
* @param string $message
* @param int $code
* @return \Illuminate\Http\JsonResponse
*/
public static function success($data = null, string $message = 'Success', int $code = 200)
{
return response()->json([
'status' => true,
'message' => $message,
'data' => $data,
], $code);
}

/**
* Error response
*
* @param string $message
* @param int $code
* @param mixed $errors
* @return \Illuminate\Http\JsonResponse
*/
public static function error(string $message = 'Error', int $code = 400, $errors = null)
{
$response = [
'status' => false,
'message' => $message,
];

if (!is_null($errors)) {
$response['errors'] = $errors;
}

return response()->json($response, $code);
}

/**
* Unauthorized response
*
* @param string $message
* @return \Illuminate\Http\JsonResponse
*/
public static function unauthorized(string $message = 'Unauthorized')
{
return self::error($message, 401);
}

/**
* Forbidden response
*
* @param string $message
* @return \Illuminate\Http\JsonResponse
*/
public static function forbidden(string $message = 'Forbidden')
{
return self::error($message, 403);
}

/**
* Not found response
*
* @param string $message
* @return \Illuminate\Http\JsonResponse
*/
public static function notFound(string $message = 'Resource not found')
{
return self::error($message, 404);
}

/**
* Validation error response
*
* @param mixed $errors
* @param string $message
* @return \Illuminate\Http\JsonResponse
*/
public static function validationError($errors, string $message = 'Validation error')
{
return self::error($message, 422, $errors);
}
}
