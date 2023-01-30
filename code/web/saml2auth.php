<?php
require_once 'bootstrap.php';
require_once 'bootstrap_aspen.php';
require_once ROOT_DIR . '/sys/Authentication/SAML2Authentication.php';
require_once ROOT_DIR . '/sys/Authentication/SSOSetting.php';
require_once ROOT_DIR . '/sys/Account/PType.php';
require_once ROOT_DIR . '/CatalogFactory.php';
require_once ROOT_DIR . '/sys/BotChecker.php';

global $library;
global $logger;

$staffPType = null;
$uidAsEmail = false;

$auth = new SAML2Authentication();

$ssoSettings = new SSOSetting();
$ssoSettings->id = $library->ssoSettingId;
$ssoSettings->service = 'saml';
if($ssoSettings->find(true)) {
	$entityId = $ssoSettings->ssoEntityId;
	if($ssoSettings->staffOnly === 1 || $ssoSettings->staffOnly === '1') {
		$staffPType = $ssoSettings->samlStaffPType;
	}
	if(($ssoSettings->staffOnly === 0 || $ssoSettings->staffOnly === '0') &&
		(!empty($ssoSettings->samlStaffPTypeAttr) && !empty($ssoSettings->samlStaffPTypeAttrValue)) &&
		($ssoSettings->samlStaffPType != '-1' || $ssoSettings->samlStaffPType != -1)) {
		$staffPType = $ssoSettings->samlStaffPType;
	}

	if(str_contains($ssoSettings->ssoUniqueAttribute, 'email')) {
		$uidAsEmail = true;
	}

} else {
	$entityId = $library->ssoEntityId;
}

// If we need to forward the user to an IdP
if (array_key_exists('samlLogin', $_REQUEST) && array_key_exists('idp', $_REQUEST) && strlen($_REQUEST['samlLogin']) > 0 && strlen($_REQUEST['idp']) > 0 && $entityId && strlen($entityId) > 0) {
	$auth->authenticate($_REQUEST['idp']);
	header('Location: /');
}

// We are processing the result of authenticating
// with the IdP

// Get the attributes from the IdP response
$usersAttributes = $auth->as->getAttributes();

// Someone has most likely hit this script directly, rather than having
// come back from an IdP
if (count($usersAttributes) == 0) {
	$logger->log("No SSO attributes found", Logger::LOG_ERROR);
	header('Location: /');
}
$logger->log(print_r($usersAttributes, true), Logger::LOG_ERROR);

// Create an associative array containing populated values
// from the supplied IdP attributes
$out = $auth->getAttributeValues();
$logger->log(print_r($out, true), Logger::LOG_ERROR);

// The user's UID
$uid = $out['ssoUniqueAttribute'];
$isStaffUser = $out['isStaffUser'] ?? false;

// Establish a connection to the LMS
$catalogConnection = CatalogFactory::getCatalogConnectionInstance();

// Get a mapping from LMS self reg property names to SSO property names
$lmsToSso = $catalogConnection->getLmsToSso($isStaffUser);

// Populate our $_REQUEST object that will be used by self reg
foreach ($lmsToSso as $key => $mappings) {
	$primaryAttr = $mappings['primary'];
	if (array_key_exists($primaryAttr, $out)) {
		$_REQUEST[$key] = $out[$primaryAttr];
	} elseif (array_key_exists('fallback', $mappings)) {
		if (strlen($mappings['fallback']) > 0) {
			$_REQUEST[$key] = $out[$mappings['fallback']];
		} else {
			$_REQUEST[$key] = $mappings['fallback'];
		}
	}
}

// Does this user exist in the LMS
if($uidAsEmail) {
	$_REQUEST['username'] = '';
	$user = $catalogConnection->findNewUserByEmail($uid);
	if(is_string($user)) {
		$logger->log($user, Logger::LOG_ERROR);
		require_once ROOT_DIR . '/services/MyAccount/StaffLogin.php';
		$launchAction = new MyAccount_StaffLogin();
		$launchAction->launch("Unable to log that user in. We found more than one account with that email address, please update the ILS to resolve.");
		exit();
	}
} else {
	$_REQUEST['username'] = $uid;
	$user = $catalogConnection->findNewUser($uid);
}

// The user does not exist in Koha, so we should create it
if (!$user instanceof User) {
	// Try to do the self reg
	$selfRegResult = $catalogConnection->selfRegister();
	// If the self reg did not succeed, log the fact
	if ($selfRegResult['success'] != '1') {
		$logger->log("Error self registering user " . $uid, Logger::LOG_ERROR);
		require_once ROOT_DIR . '/services/MyAccount/StaffLogin.php';
		$launchAction = new MyAccount_StaffLogin();
		$launchAction->launch('Unable to create a new user in the ILS.');
		exit();
	}
	// The user now exists in the LMS, so findNewUser should create an Aspen user
	if($uidAsEmail) {
		$user = $catalogConnection->findNewUserByEmail($uid);
	} else {
		$user = $catalogConnection->findNewUser($uid);
	}
} else {
	// We need to update the user in the LMS
	$user = $user->updatePatronInfo(true);
	// findNewUser forces Aspen to update it's user with that of the LMS
	if($uidAsEmail) {
		$user = $catalogConnection->findNewUserByEmail($uid);
	} else {
		$user = $catalogConnection->findNewUser($uid);
	}
}

// If we have an Aspen user, we can set up the session
if ($user instanceof User) {
	$login = UserAccount::login(true);

	global $configArray;
	global $timer;
	$session_type = $configArray['Session']['type'];
	$session_lifetime = $configArray['Session']['lifetime'];
	$session_rememberMeLifetime = $configArray['Session']['rememberMeLifetime'];
	$sessionClass = ROOT_DIR . '/sys/Session/' . $session_type . '.php';
	require_once $sessionClass;

	if (class_exists($session_type)) {
		session_destroy();
		session_name('aspen_session'); // must also be set in index.php, in initializeSession()
		/** @var SessionInterface $session */
		$session = new $session_type();
		$session->init($session_lifetime, $session_rememberMeLifetime);
	}

	$_SESSION['activeUserId'] = $login->id;
	$_SESSION['rememberMe'] = false;
	$_SESSION['loggedInViaSSO'] = true;
}

// Redirect the user to the homepage
header('Location: /');