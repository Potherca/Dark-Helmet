<?php
/**
 * @author:	 Potherca <potherca@gmail.com>
 * @package: DarkHelmet
 * @link:	 https://github.com/potherca/Dark-Helmet/
 * @license: Copyright 2011, Potherca
 *
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/gpl.html>.
 *
 */
namespace DarkHelmet
{
	date_default_timezone_set("Europe/Amsterdam");

	use DarkHelmet\Core\Exception;
	use DarkHelmet\Core\Request;
	use DarkHelmet\Core\Settings;

	use DarkHelmet\Core\Controllers\Base as BaseController;

////////////////////////////////////////////////////////////////////////////////
//	I'm pretty sure there's a nicer solution for this... either move it to a
//	separate bootstrap file, include it in the controller or get rid of it altogether.
//	Especially the 'require' and the constants are a bit of an eye-sore to me.
//----------------------------------------------------------------------------//
	error_reporting(E_STRICT | E_ALL);

	set_error_handler(
		function($p_iError, $p_sError, $p_sFile, $p_iLine ) {
			throw new \ErrorException($p_sError, 0, $p_iError, $p_sFile, $p_iLine);
		}
	);

	define('PROJECT_DIR',   realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
	define('LOGS_DIR',      PROJECT_DIR . 'logs/');
	define('TEMPLATE_DIR',  PROJECT_DIR . 'lib/Templates/');
	define('CONFIG_DIR',	PROJECT_DIR . 'conf/');

	require '3rd-party/PHPTAL/classes/PHPTAL.php'; // Template Autoloader

	spl_autoload_register(
		function($p_sFullyQualifiedClassName){
			if(strpos($p_sFullyQualifiedClassName, 'DarkHelmet') === 0){

				$sFullClassName = str_replace('\\', '/', $p_sFullyQualifiedClassName);
				$sClassName = substr($sFullClassName, strrpos($sFullClassName, '/')+1);
				$sNameSpace = substr($sFullClassName, strlen('DarkHelmet'), -strlen($sClassName));

				if(strpos($sClassName,'Exception') !== false){
					$sClassName = 'Exceptions';
				}

				$aFilePrefixes = array('class','abstract','interface');
				foreach($aFilePrefixes as $t_sPrefix){
					$sFilePath = PROJECT_DIR . 'lib' . $sNameSpace . $t_sPrefix . '.' . $sClassName .'.php';
					if(file_exists($sFilePath)){
						require_once $sFilePath;
						break;
					}#if
				}#foreach
			}
			else{
				// fall through to the next autoloader
			}#if
		}#function
	);
////////////////////////////////////////////////////////////////////////////////
	$sUrl = $_SERVER['REQUEST_URI'];
	$aPostFields = array();
	if(isset($_POST['tags'])) {
		$aPostFields = $_POST['tags'];
	}
	$oRequest  = Request::get($sUrl, $aPostFields);
	
	// This is a temporary solution so people get informed that the config files have moved.
	if(!is_file(CONFIG_DIR . 'settings.xml') && is_file(PROJECT_DIR . 'settings.xml')) {
		// Configuration files are not yet moved. Inform the world!
		
		$oSettings = Settings::loadFromFile(PROJECT_DIR . 'settings.xml');
		$sPredefined = '';
		try {
			$sPredefined = $oSettings->get('Connectors/Connector/Params/TagFilePath');
		} catch(\Exception $ex) {
			// Path not found, so not configured. Ignore.
		}
		
		$sOutput  = '<h1>Important</h1>';
		$sOutput .= '<p>The configuration files need to be moved from
						the project root folder ( ' . PROJECT_DIR . ' )
						into the conf folder.<br/>
						This concerns the following files:<p>';
		$sOutput .= '<ul>
						<li><strong>settings.xml</strong></li>
						<li><strong>credentials.xml</strong></li>
					</ul>';
		if($sPredefined !== '') {
			$sOutput .= '<p>If you also want to move <strong>'.$sPredefined.'</strong>
						into the conf folder, be sure to add the path to the file
						name in settings.xml</p>';
		}#if
		$sOutput .= '<p>After you have moved the files, please reload this page.</p>';
		
	} else {
		$oSettings = Settings::loadFromFile(CONFIG_DIR . 'settings.xml');
		$oSettings->credentialsFromFile(CONFIG_DIR . 'credentials.xml');

		$sOutput = BaseController::getResponse($oRequest, $oSettings);
	}#if
	
	die ($sOutput);
}

#EOF
