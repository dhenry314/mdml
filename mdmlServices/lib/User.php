<?php
namespace mdml;
use \Firebase\JWT\JWT;

class User {

  var $type = "mdml/User";

  public static function auth($config,$user,$pw) {
    include("../Users.php");
    if(!array_key_exists($user,$Users)) return FALSE;
    if($Users[$user]['password'] == $pw) {
      $data = array();
      $data['iss'] = $config['JWT_ISSUER'];
      $data['iat'] = time();
      $data['exp'] = time() + $config['JWT_TTL_SECS'];
      $data['aud'] = $Users[$user]['paths'];
      return $data;
    }
    return FALSE;
  }
  
  public static function getJWT() {
    $authParts = explode(' ',$_SERVER['HTTP_AUTHORIZATION']);
    array_shift($authParts);
    $jwt = array_pop($authParts);  
    return $jwt;
  }

  public static function getJWTPayload($config) {
    $jwt = User::getJWT();
    $jwt = str_replace(array("\r", "\n"), '', $jwt);
    try {
		$decoded = JWT::decode($jwt, $config['JWT_SECRET'], array('HS256'));
	}
	catch (\Exception $e) {
		$msg = "ERROR: ";
		if(strstr($e->getMessage(),'Expired')) {
				$msg .= "Token is expired!  Please go to " . $config['loginService'] . " to get a token.";
		} else {
				$msg .= "Invalid token! " . $e->getMessage();
		}
		header("HTTP/1.0 403 Forbidden");
		header('Content-Type: application/json');
		echo json_encode(array(
			'ErrorMessage' => $msg
		));
    	die();
	}
    return $decoded;
  }

}

?>
