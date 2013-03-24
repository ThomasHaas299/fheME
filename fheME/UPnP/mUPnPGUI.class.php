<?php
/**
 *  This file is part of fheME.

 *  fheME is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  fheME is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses></http:>.
 * 
 *  2007 - 2013, Rainer Furtmeier - Rainer@Furtmeier.IT
 */

class mUPnPGUI extends anyC implements iGUIHTMLMP2 {

	public function getHTML($id, $page){
		$this->loadMultiPageMode($id, $page, 0);

		$gui = new HTMLGUIX($this);
		$gui->version("mUPnP");

		$gui->name("UPnP");
		
		$gui->attributes(array("UPnPName"));
		
		$B = $gui->addSideButton("Geräte\nerkennen", "lieferschein");
		$B->popup("", "UPnP-Geräte", "mUPnP", "-1", "discover");
		
		$B = $gui->addSideButton("Remote\nanzeigen", "./fheME/UPnP/remote.png");
		$B->onclick("UPnP.show();");
		
		return $gui->getBrowserHTML($id);
	}

	public function remote(){
		
		echo "
		<div id=\"UPnPSelection\">
			<div style=\"float:right;\">
				<div onclick=\"UPnP.hide();\" style=\"cursor:pointer;float:left;font-family:Roboto;font-size:30px;padding:10px;\">
					<span>Schließen</span> <span style=\"font-size:30px;margin-right:10px;\" class=\"iconic x\"></span>
				</div>
			</div>
			
			<div style=\"width:35%;float:left;\">
				<div onclick=\"UPnP.targetSelection();\" style=\"cursor:pointer;font-family:Roboto;font-size:30px;padding:10px;\">
					<span id=\"UPnPTargetName\">Abspielgerät auswählen</span> <span style=\"font-size:30px;\" class=\"iconic arrow_down\"></span>
				</div>
			</div>
			
			<div style=\"display:none;float:left;width:35%;\">
				<div onclick=\"UPnP.sourceSelection();\" style=\"cursor:pointer;float:left;font-family:Roboto;font-size:30px;padding:10px;\">
					<span id=\"UPnPSourceName\">Quelle auswählen</span> <span style=\"font-size:30px;\" class=\"iconic arrow_down\"></span>
				</div>
			</div>

			<div style=\"clear:both;\">
			</div>
		</div>
		<div id=\"UPnPTargetSelection\" style=\"padding:10px;display:none;\"></div>
		<div id=\"UPnPSourceSelection\" style=\"padding:10px;display:none;margin-left:35%;\"></div>
		<div id=\"UPnPMediaSelection\" style=\"padding:10px;padding-right:0px;width:200%;overflow:hidden;\"></div>";
	}
	
	public function getTargets(){
		$this->addAssocV3("UPnPAVTransport", "=", "1");
		
		$L = new HTMLList();
		$L->addListStyle("font-size:30px;font-family:Roboto;margin-left:10px;");
		while($T = $this->getNextEntry()){
			$L->addItem($T->A("UPnPName"));
			
			$L->addItemEvent("onclick", "UPnP.selectTarget('".$T->getID()."', '".$T->A("UPnPName")."');");
			$L->addItemStyle("cursor:pointer;padding:10px;");
		}
		
		echo $L;
	}
	
	public function getSources(){
		$this->addAssocV3("UPnPContentDirectory", "=", "1");
		
		$L = new HTMLList();
		$L->addListStyle("font-size:30px;font-family:Roboto;margin-left:10px;");
		while($T = $this->getNextEntry()){
			$L->addItem($T->A("UPnPName"));
			
			$L->addItemEvent("onclick", "UPnP.selectSource('".$T->getID()."', '".$T->A("UPnPName")."');");
			$L->addItemStyle("cursor:pointer;padding:10px;");
		}
		
		echo $L;
	}
	
	public function discover(){
		echo "<p>Starte Suche. Das könnte etwas dauern...</p>";
		echo OnEvent::script(OnEvent::popup("", "mUPnP", "-1", "discoverNow"));
	}
	
	public static $desiredServices = array("AVTransport" => "urn:upnp-org:serviceId:AVTransport", "ContentDirectory" => "urn:upnp-org:serviceId:ContentDirectory", "ConnectionManager" => "urn:upnp-org:serviceId:ConnectionManager");
	
	public function discoverNow(){
		$C = new phpUPnP();
		#echo "<pre style=\"padding:5px;font-size:9px;overflow:auto;height:400px;\">";
		$result = $C->mSearch();
		echo "<p>Gefundene Geräte:</p>";
		#$locations = array();
		$L = new HTMLList();
		$foundLocations = array();
		
		foreach($result AS $r){
			if(isset($foundLocations[$r["location"]]))
				continue;
			
			$info = file_get_contents($r["location"]);
			if($info === false)
				continue;
			
			$xml = new SimpleXMLElement($info);
			
			$services = array();
			foreach ($xml->device->serviceList->service AS $service){
				foreach(self::$desiredServices AS $k => $S)
					if($service->serviceId[0] == $S)
						$services[$k] = $service;
			}
			
			
			$F = new Factory("UPnP");
			$F->sA("UPnPLocation", $r["location"]);
			$L->addItem($xml->device->friendlyName);
			
			$U = $F->exists(true);
			if($U !== false){
				$U->changeA("UPnPName", $xml->device->friendlyName);
				
				foreach(self::$desiredServices AS $S => $nil)
					$U->changeA("UPnP$S", 0);
				
				foreach($services AS $S => $service){
					$U->changeA("UPnP$S", 1);
					$U->changeA("UPnP".$S."SCPDURL", $service->SCPDURL[0]."");
					$U->changeA("UPnP".$S."controlURL", $service->controlURL[0]."");
				}
				$U->saveMe();
			} else {
				$F->sA("UPnPName", $xml->device->friendlyName);
				
				foreach(self::$desiredServices AS $S => $nil)
					$F->sA("UPnP$S", 0);
				
				foreach($services AS $S => $service){
					$F->sA("UPnP$S", 1);
					$F->sA("UPnP".$S."SCPDURL", $service->SCPDURL[0]."");
					$F->sA("UPnP".$S."controlURL", $service->controlURL[0]."");
				}
				
				$F->store();
			}
			
			
			$foundLocations[$r["location"]] = true;
		}
		
		$AC = anyC::get("UPnP");
		while($U = $AC->getNextEntry()){
			if(!isset($foundLocations[$U->A("UPnPLocation")]))
				$U->deleteMe();
		}
		
		echo $L;
		#echo "</pre>";
	}
}
?>