<?php
/*
 *  This file is part of open3A.

 *  open3A is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  open3A is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  2007 - 2017, Furtmeier Hard- und Software - Support@Furtmeier.IT
 */
die("REMOVE THIS LINE TO ACTIVATE THE SERVER!");
session_name("ExtConn");
error_reporting(E_ALL);
/**
 * The folder that contains the dirs applications, classes, images, plugins, system...
 */
$absolutePathToPhynx = "/home/nemiah/NetBeansProjects/phxnx/";

require_once $absolutePathToPhynx."classes/frontend/ExtConn.class.php";
require_once $absolutePathToPhynx."fheME/Fhem/ExtConnFhem.class.php";

class ExtConnFhemSOAPServer extends ExtConn {
	function __construct(){
		GLOBAL $absolutePathToPhynx;

		parent::__construct($absolutePathToPhynx);

		$this->useDefaultMySQLData();
		$this->useUser();

		$this->loadPluginInterface("fheME/Fhem", "ExtConnFhem");
	}

	function __destruct() {
		$this->cleanUp();
	}
}

$S = new SoapServer(null, array('uri' => 'http://phynx/'));#, 'soap_version' => SOAP_1_2
$S->setClass('ExtConnFhemSOAPServer');
$S->handle();
?>