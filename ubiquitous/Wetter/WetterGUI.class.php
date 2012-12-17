<?php
/**
 *  This file is part of ubiquitous.

 *  ubiquitous is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  ubiquitous is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 *  2007 - 2012, Rainer Furtmeier - Rainer@Furtmeier.de
 */
class WetterGUI extends Wetter implements iGUIHTML2 {
	function getHTML($id){
		$gui = new HTMLGUIX($this);
		$gui->name("Wetter");
	
		$B = $gui->addSideButton("Daten\nanzeigen", "empty");
		$B->popup("", "Wetter-Daten", "Wetter", $this->getID(), "getData");
		
		#$gui->descriptionField("WetterWOEID", "Siehe <a href=\"http://isithackday.com/geoplanet-explorer/\" target=\"_blank\">http://isithackday.com/geoplanet-explorer/</a>");
		
		return $gui->getEditHTML();
	}
	
	public function getData() {
		echo "<pre style=\"height:400px;overflow:auto;\">";
		print_r(parent::getData());
		echo "</pre>";
	}
}
?>