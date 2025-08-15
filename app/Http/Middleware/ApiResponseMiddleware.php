<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApiResponseMiddleware
{
	public function handle(Request $request, Closure $next)
	{
		$response = $next($request);

		// Normalize only JSON responses (this middleware is bound to the 'api' group)
		if ($response instanceof JsonResponse) {
			$status = $response->getStatusCode();
			$originalData = $response->getData(true);

			// If already standardized, ensure 'data' is present (default to empty array) and return
			if (is_array($originalData)
				&& array_key_exists('status', $originalData)
				&& array_key_exists('success', $originalData)
				&& array_key_exists('message', $originalData)
			) {
				if (!array_key_exists('data', $originalData) || $originalData['data'] === null) {
					$originalData['data'] = [];
				}
				$headers = $response->headers->all();
				return new JsonResponse($originalData, $status, $headers);
			}

			$success = $status >= 200 && $status < 300;
			$message = $this->inferMessage($originalData, $status);

			$wrapped = [
				'status' => $status,
				'success' => $success,
				'message' => $message,
				'data' => $this->normalizeData($success ? $originalData : null),
			];

			// Preserve headers
			$headers = $response->headers->all();
			return new JsonResponse($wrapped, $status, $headers);
		}

		return $response;
	}

	private function inferMessage(mixed $data, int $status): string
	{
		if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
			return $data['message'];
		}
		return SymfonyResponse::$statusTexts[$status] ?? 'OK';
	}

	private function normalizeData(mixed $data): array
	{
		if ($data === null) {
			return [];
		}
		if (is_array($data)) {
			return $data;
		}
		// Fallback: try to cast to array, otherwise wrap it
		if (is_object($data)) {
			return (array) $data;
		}
		return ['value' => $data];
	}
}


