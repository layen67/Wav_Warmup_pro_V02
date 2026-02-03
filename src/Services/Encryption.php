<?php

namespace PostalWarmup\Services;

/**
 * Service de chiffrement des données sensibles (Clés API)
 */
class Encryption {

	/**
	 * Méthode de chiffrement
	 */
	private const METHOD = 'AES-256-CBC';

	/**
	 * Récupère la clé de chiffrement
	 * Utilise SECURE_AUTH_KEY si disponible, sinon un hash du site_url
	 */
	private static function get_key(): string {
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			return SECURE_AUTH_KEY;
		}
		return hash( 'sha256', get_site_url() );
	}

	/**
	 * Chiffre une chaîne
	 *
	 * @param string $data La donnée à chiffrer.
	 * @return string La donnée chiffrée en base64 (IV + Data).
	 */
	public static function encrypt( string $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		$key = hash( 'sha256', self::get_key() );
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $data, self::METHOD, $key, 0, $iv );

		// On retourne l'IV et le texte chiffré, encodés en base64 pour le stockage
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Déchiffre une chaîne
	 *
	 * @param string $data La donnée chiffrée (base64).
	 * @return string La donnée en clair, ou la donnée originale si échec.
	 */
	public static function decrypt( string $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		// Tenter de décoder le base64
		$raw = base64_decode( $data, true );
		if ( $raw === false ) {
			// Ce n'est pas du base64 valide, c'est peut-être une ancienne clé non chiffrée
			return $data;
		}

		$key = hash( 'sha256', self::get_key() );
		$iv_length = openssl_cipher_iv_length( self::METHOD );

		// Vérifier que la longueur est suffisante pour contenir l'IV
		if ( strlen( $raw ) < $iv_length ) {
			return $data;
		}

		$iv = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$decrypted = openssl_decrypt( $ciphertext, self::METHOD, $key, 0, $iv );

		// Si le déchiffrement échoue, on retourne la donnée originale (transition douce)
		if ( $decrypted === false ) {
			return $data;
		}

		return $decrypted;
	}

	/**
	 * Vérifie si une chaîne semble chiffrée (heuristique simple)
	 */
	public static function is_encrypted( string $data ): bool {
		$raw = base64_decode( $data, true );
		if ( $raw === false ) {
			return false;
		}
		// Longueur minimum (IV + au moins un bloc)
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		return strlen( $raw ) > $iv_length;
	}
}
