<?php
/**
 *  This file is part of plugins.

 *  plugins is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  plugins is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 *  2007 - 2013, Rainer Furtmeier - Rainer@Furtmeier.IT
 */
class DeviceGUI extends Device implements iGUIHTML2 {
	function getHTML($id){
		$gui = new HTMLGUIX($this);
		$gui->name("Device");
	
		$B = $gui->addSideButton("Browser\nregistrieren", "./plugins/Devices/registerDevice.png");
		$B->onclick("\$j.jStorage.set('phynxDeviceID','".$this->getID()."');".OnEvent::reload("Left"));
			
		$B = $gui->addSideButton("Übersicht\neinrichten", "./fheME/Overview/fheOverview.png");
		$B->popup("", "Übersicht einrichten", "mfheOverview", "-1", "manage", $this->getID(), "", "{width:800, top:40}");
		
		return $gui->getEditHTML();
	}
}
?>