<?php

/**
 * Support function -- get the file path to one of the ini files specified in the
 * [Extra_Config] section of config.ini.
 *
 * @param   string $name        The ini's name from the [Extra_Config] section of config.ini
 * @return  string      The file path
 */
function getExtraConfigArrayFile($name)
{
	global $configArray;

	// Load the filename from config.ini, and use the key name as a default
	//     filename if no stored value is found.
	$filename = isset($configArray['Extra_Config'][$name]) ? $configArray['Extra_Config'][$name] : $name . '.ini';

	//Check to see if there is a domain name based subfolder for he configuration
	global $serverName;
	if (file_exists(ROOT_DIR . "/../../sites/$serverName/conf/$filename")){
		// Return the file path (note that all ini files are in the conf/ directory)
		return ROOT_DIR . "/../../sites/$serverName/conf/$filename";
	}elseif (file_exists(ROOT_DIR . "/../../sites/default/conf/$filename")){
		// Return the file path (note that all ini files are in the conf/ directory)
		return ROOT_DIR . "/../../sites/default/conf/$filename";
	} else{
		// Return the file path (note that all ini files are in the conf/ directory)
		return ROOT_DIR . '/../../sites/' . $filename;
	}

}

/**
 * Load a translation map from the translation_maps directory
 *
 * @param   string $name        The name of the translation map should not include _map.properties
 * @return  string      The file path
 */
function getTranslationMap($name)
{
	//Check to see if there is a domain name based subfolder for he configuration
	global $serverName;
	/** @var Memcache $memCache */
	global $memCache;
	$mapValues = $memCache->get('translation_map_'. $serverName.'_'. $name);
	if ($mapValues != false && $mapValues != null && !isset($_REQUEST['reload'])){
		return $mapValues;
	}

	// If the requested settings aren't loaded yet, pull them in:
	$mapNameFull = $name . '_map.properties';
	if (file_exists("../../sites/$serverName/translation_maps/$mapNameFull")){
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = "../../sites/$serverName/translation_maps/$mapNameFull";
	}elseif (file_exists("../../sites/default/translation_maps/$mapNameFull")){
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = "../../sites/default/translation_maps/$mapNameFull";
	} else{
		// Return the file path (note that all ini files are in the conf/ directory)
		$mapFilename = '../../sites/' . $mapNameFull;
	}


	// Try to load the .ini file; if loading fails, the file probably doesn't
	// exist, so we can treat it as an empty array.
	$mapValues = array();
	$fHnd = fopen($mapFilename, 'r');
	while (($line = fgets($fHnd)) !== false){
		if (substr($line, 0, 1) == '#'){
			//skip the line, it's a comment
		}else{
			$lineData = explode('=', $line, 2);
			if (count($lineData) == 2){
				$mapValues[strtolower(trim($lineData[0]))] = trim($lineData[1]);
			}
		}
	}
	fclose($fHnd);

	global $configArray;
	$memCache->set('translation_map_'. $serverName.'_' . $name, $mapValues, 0, $configArray['Caching']['translation_map']);
	return $mapValues;
}

function mapValue($mapName, $value){
	$map = getTranslationMap($mapName);
	if ($map == null || $map == false){
		return $value;
	}
	$value = str_replace(' ', '_', $value);
	if (isset($map[$value])){
		return $map[$value];
	}elseif (isset($map[strtolower($value)])){
		return $map[strtolower($value)];
	}elseif(isset($map['*'])){
		if ($map['*'] == 'nomap'){
			return $value;
		}else{
			return $map['*'];
		}
	}else{
		return '';
	}
}

/**
 * Support function -- get the contents of one of the ini files specified in the
 * [Extra_Config] section of config.ini.
 *
 * @param   string $name        The ini's name from the [Extra_Config] section of config.ini
 * @return  array       The retrieved configuration settings.
 */
function getExtraConfigArray($name)
{
	static $extraConfigs = array();

	// If the requested settings aren't loaded yet, pull them in:
	if (!isset($extraConfigs[$name])) {
		// Try to load the .ini file; if loading fails, the file probably doesn't
		// exist, so we can treat it as an empty array.
		$extraConfigs[$name] = @parse_ini_file(getExtraConfigArrayFile($name), true);
		if ($extraConfigs[$name] === false) {
			$extraConfigs[$name] = array();
		}
	}

	return $extraConfigs[$name];
}

/**
 * Support function -- merge the contents of two arrays parsed from ini files.
 *
 * @param   array $config_ini  The base config array.
 * @param   array $custom_ini  Overrides to apply on top of the base array.
 * @return  array       The merged results.
 */
function ini_merge($config_ini, $custom_ini)
{
	foreach ($custom_ini as $k => $v) {
		if (is_array($v)) {
			$config_ini[$k] = ini_merge(isset($config_ini[$k]) ? $config_ini[$k] : array(), $custom_ini[$k]);
		} else {
			$config_ini[$k] = $v;
		}
	}
	return $config_ini;
}

/**
 * Support function -- load the main configuration options, overriding with
 * custom local settings if applicable.
 *
 * @return  array       The desired config.ini settings in array format.
 */
function readConfig()
{
    //Read default configuration file
    $configFile = ROOT_DIR . '/../../sites/default/conf/config.ini';
    $mainArray = parse_ini_file($configFile, true);

    global $fullServerName, $serverName, $instanceName;

    if (isset($_SERVER['aspen_server'])) {
        //Override withing the config file
        $fullServerName = $_SERVER['aspen_server'];
        //echo("Server name is set as server var $serverUrl\r\n");
    } else {

        if (isset($_SERVER['SERVER_NAME'])) {
            //Run from browser
            $fullServerName = $_SERVER['SERVER_NAME'];
        }elseif (count($_SERVER['argv']) > 1) {
            $fullServerName = $_SERVER['argv'][1];
        }else{
            die('No server name could be found to load configuration');
        }

    }

	$server = $fullServerName;
	$serverParts = explode('.', $server);
	$serverName = 'default';
	while (count($serverParts) > 0){
		$tmpServername = join('.', $serverParts);
		$configFile = ROOT_DIR . "/../../sites/$tmpServername/conf/config.ini";
		if (file_exists($configFile)){
			$serverArray = parse_ini_file($configFile, true);
			$mainArray = ini_merge($mainArray, $serverArray);
			$serverName = $tmpServername;

			$passwordFile = ROOT_DIR . "/../../sites/$tmpServername/conf/config.pwd.ini";
			if (file_exists($passwordFile)){
				$serverArray = parse_ini_file($passwordFile, true);
				$mainArray = ini_merge($mainArray, $serverArray);
			}
		}

		array_shift($serverParts);
	}

	// Sanity checking to make sure we loaded a good file
	// @codeCoverageIgnoreStart
	if ($serverName == 'default'){
		global $logger;
		if ($logger){
			$logger->log('Did not find servername for server ' . $fullServerName, Logger::LOG_ERROR);
		}
		AspenError::raiseError("Invalid configuration, could not find site for " . $fullServerName);
	}

	if ($mainArray == false){
		echo("Unable to parse configuration file $configFile, please check syntax");
	}
	// @codeCoverageIgnoreEnd

	// Set a instanceName so that memcache variables can be stored for a specific instance of Pika,
	// rather than the $serverName will depend on the specific interface a user is browsing to.
	$instanceName = parse_url($mainArray['Site']['url'], PHP_URL_HOST);
		// Have to set the instanceName before the transformation of $mainArray['Site']['url'] below.

	//If we are accessing the site via a subdomain, need to preserve the subdomain
	//Don't try to preserve SSL since the combination of proxy and SSL does not work nicely.
	//i.e. https://mesa.marmot.org is proxied to https://mesa.opac.marmot.org which does not have
	//a valid SSL cert

	//We no longer are doing proxies as described above so we can preserve SSL now.
    if (isset($_SERVER['SERVER_NAME'])){
        if (isset($_SERVER['HTTPS'])){
            $mainArray['Site']['url'] = "https://" . $_SERVER['SERVER_NAME'];
        }else{
            $mainArray['Site']['url'] = "http://" . $_SERVER['SERVER_NAME'];
        }
    }

	return $mainArray;
}

/**
 * Update the configuration array as needed based on scoping rules defined
 * by the subdomain.
 *
 * @param array $configArray the existing main configuration options.
 *
 * @return array the configuration options adjusted based on the scoping rules.
 */
function updateConfigForScoping($configArray) {
	global $timer;
    global $fullServerName;

    //Get the subdomain for the request
	if (isset($_REQUEST['test_servername'])) {
        $fullServerName = $_GET['test_servername'];
        if (empty($fullServerName)) {
            setcookie('test_servername', $_COOKIE['test_servername'], time() - 1000, '/');
        } else if (!isset($_COOKIE['test_servername']) || ($_COOKIE['test_servername'] != $fullServerName)){
            setcookie('test_servername', $fullServerName, 0, '/');
        }
    }else if(isset($_COOKIE['test_servername'])) {
        $fullServerName = $_COOKIE['test_servername'];
    }

	//split the servername based on
	global $subdomain;
	$subdomain = null;
	$timer->logTime('starting updateConfigForScoping');

	$subdomainsToTest = array();
	if(strpos($fullServerName, '.')){
		$serverComponents = explode('.', $fullServerName);
		$tempSubdomain = '';
		if (count($serverComponents) >= 3){
			//URL is probably of the form subdomain.marmot.org or subdomain.opac.marmot.org
			$subdomainsToTest[] = $serverComponents[0];
			$tempSubdomain = $serverComponents[0];
		} else if (count($serverComponents) == 2){
			//URL could be either subdomain.localhost or marmot.org. Only use the subdomain
			//If the second component is localhost.
			if (strcasecmp($serverComponents[1], 'localhost') == 0){
				$subdomainsToTest[] = $serverComponents[0];
				$tempSubdomain = $serverComponents[0];
			}
		}
		//Trim off test indicator when doing lookups for library/location
		$lastChar = substr($tempSubdomain, -1);
		if ($lastChar == '2' || $lastChar == '3' || $lastChar == 't' || $lastChar == 'd' || $lastChar == 'x'){
			$subdomainsToTest[] = substr($tempSubdomain, 0, -1);
		}
	}

	$timer->logTime('found ' . count($subdomainsToTest) . ' subdomains to test');

    echo("Loading global library");
    echo("Active Library is: " . $_SERVER['active_library']);

	//Load the library system information
	global $library;
	global $locationSingleton;
	if (isset($_SESSION['library']) && isset($_SESSION['location'])) {
        $library = $_SESSION['library'];
        $locationSingleton = $_SESSION['library'];
        $timer->logTime('got library and location from session');
    }else if (count($subdomainsToTest) == 0) {
        $Library = new Library();
        $Library->isDefault = 1;
        $Library->find();
        if ($Library->N == 1) {
            $Library->fetch();
            $library = $Library;
        }
        //Next check for an active_library server environment variable
    }elseif (isset($_SERVER['active_library'])){
        $Library = new Library();
        $Library->subdomain = $_SERVER['active_library'];
        $Library->find($_SERVER['active_library']);
        if ($Library->N == 1) {
            $Library->fetch();
            $library = $Library;
            $timer->logTime("found the library based on active_library server variable");
        }
	}else {
		for ($i = 0; $i < count($subdomainsToTest); $i++){
			$subdomain = $subdomainsToTest[$i];
			$timer->logTime("testing subdomain $i $subdomain");
			$Library = new Library();
			$timer->logTime("created new library object");
			$Library->subdomain = $subdomain;
			$Library->find();
			$timer->logTime("searched for library by subdomain $subdomain");

			if ($Library->N == 1) {
				$Library->fetch();
				//Make the library information global so we can work with it later.
				$library = $Library;
				$timer->logTime("found the library based on subdomain");
				break;
			} else {
				//The subdomain can also indicate a location.
				$Location = new Location();
				$Location->whereAdd("code = '$subdomain'");
				$Location->whereAdd("subdomain = '$subdomain'", 'OR');
				$Location->find();
				if ($Location->N == 1) {
					$Location->fetch();
					//We found a location for the subdomain, get the library.
					/** @var Library $librarySingleton */
					global $librarySingleton;
					$library = $librarySingleton->getLibraryForLocation($Location->locationId);
					$locationSingleton->setActiveLocation(clone $Location);
					$timer->logTime("found the location and library based on subdomain");
					break;
				} else {
					//Check to see if there is only one library in the system
					$Library = new Library();
					$Library->find();
					if ($Library->N == 1) {
						$Library->fetch();
						$library = $Library;
						$timer->logTime("there is only one library for this install");
						break;
					} else {
						//If we are on the last subdomain to test, grab the default.
						if ($i == count($subdomainsToTest) - 1){
							//Get the default library
							$Library = new Library();
							$Library->isDefault = 1;
							$Library->find();
							if ($Library->N == 1) {
								$Library->fetch();
								$library = $Library;
								$timer->logTime("found the library based on the default");
							} else {
								echo("Could not determine the correct library to use for this install");
							}
						}
					}
				}
			}
		}
	}
	$timer->logTime('found library and location');
	if ($library == null) {
	    echo("Could not find the active library, please review configuration settings");
	    die();
    } else {
		//Update the title
		$configArray['Site']['theme'] = $library->themeName . ',' . $configArray['Site']['theme'] . ',default';
		$configArray['Site']['title'] = $library->displayName;

		$location = $locationSingleton->getActiveLocation();
		$timer->logTime('found active location');

		$timer->logTime('loaded themes');
	}
	$timer->logTime('finished update config for scoping');

	return $configArray;
}
