<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GEEKYBOT_SupportTicketServerCalls extends GEEKYBOT_Updater{

	private static $server_url = 'https://geekybot.com/setup/index.php';

	public static function GEEKYBOT_PluginUpdateCheck($token_arrray_json) {
		$args = array(
			'request' => 'pluginupdatecheck',
			'token' => $token_arrray_json,
			'domain' => site_url()
		);

		$url = self::$server_url . '?' . http_build_query( $args, '', '&' );
		$request = wp_remote_get($url);

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			$error_message = 'pluginupdatecheck case returned error';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode($response);

		if ( is_object( $response ) ) {
			return $response;
		} else {
			$error_message = 'pluginupdatecheck case returned data which was not correct';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}
	}

	public static function GEEKYBOT_PluginUpdateCheckFromCDN() {
		
		$url = "https://d2cmrbf1z3cvtf.cloudfront.net/addonslatestversions.txt";
		$request = wp_remote_get($url);

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			$error_message = 'pluginupdatecheck cdn case returned error';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode($response);

		if ( is_object( $response ) ) {
			return $response;
		} else {
			$error_message = 'pluginupdatecheck cdn case returned data which was not correct';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}
	}

	public static function GEEKYBOT_GenerateToken($transaction_key,$addon_name) {
			$args = array(
				'request' => 'generatetoken',
				'transactionkey' => $transaction_key,
				'productcode' => $addon_name,
				'domain' => site_url()
			);

			$url = self::$server_url . '?' . http_build_query( $args, '', '&' );
			$request = wp_remote_get($url);
			if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
				$error_message = 'generatetoken case returned error';
				// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
				return array('error'=>$error_message);
			}

			$response = wp_remote_retrieve_body( $request );
			$response = json_decode($response,true);

			if ( is_array( $response ) ) {
				return $response;
			} else {
				$error_message = 'generatetoken case returned data which was not correct';
				// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
				return array('error'=>$error_message);
			}
			return false;
		}


	public static function GEEKYBOT_GetLatestVersions() {
		$args = array(
				'request' => 'getlatestversions'
			);
		$request = wp_remote_get( 'https://geekybot.com/appsys/addoninfo/index.php' . '?' . http_build_query( $args, '', '&' ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			$error_message = 'getlatestversions case returned error';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode($response,true);
		if ( is_array( $response ) ) {
			return $response;
		} else {
			$error_message = 'getlatestversions case returned data which was not correct';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}
	}

	public static function GEEKYBOT_PluginInformation( $args ) {
		$defaults = array(
			'request'        => 'plugininformation',
			'plugin_slug'    => '',
			'version'        => '',
			'token'    => '',
			'domain'          => site_url()
		);

		$args    = wp_parse_args( $args, $defaults );
		$request = wp_remote_get( 'https://geekybot.com/appsys/addoninfo/index.php' . '?' . http_build_query( $args, '', '&' ) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
			$error_message = 'plugininformation case returned data error';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}
		$response = wp_remote_retrieve_body( $request );

		$response = json_decode($response);

		if ( is_object( $response ) ) {
			return $response;
		} else {
			$error_message = 'plugininformation case returned data which is not correct';
			// GEEKYBOTincluder::GEEKYBOT_getModel('systemerror')->addSystemError($error_message);
			return false;
		}
	}
}
