<?php

class JWT
{
	/**
	 * @param $projectId
	 * @param $secret
	 * @return string
	 */
	public static function createToken($projectId, $secret): string
	{
		$header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

		$payload = json_encode([
			'iat'       => time(),
			'exp'       => time() + 3600,
			"projectId" => $projectId
		]);

		$base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

		$base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

		$signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);

		$base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

		return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
	}
}
