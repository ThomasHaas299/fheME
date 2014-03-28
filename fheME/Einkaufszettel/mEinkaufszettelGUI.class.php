<?php
/**
 *  This file is part of wasGibtsMorgen.

 *  wasGibtsMorgen is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  wasGibtsMorgen is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 *  2007, 2008, 2009, 2010, 2011, Rainer Furtmeier - Rainer@Furtmeier.de
 */

class mEinkaufszettelGUI extends anyC implements iGUIHTMLMP2 {

	public function getHTML($id, $page){
		$this->addOrderV3("EinkaufszettelTime", "DESC");
		$this->loadMultiPageMode($id, $page, 0);

		$gui = new HTMLGUIX($this);
		$gui->version("mEinkaufszettel");
		$gui->colWidth("EinkaufszettelBought", 20);
		
		$gui->name("Einkaufszettel");
		$gui->parser("EinkaufszettelBought", "Util::catchParser");
		$gui->attributes(array("EinkaufszettelBought", "EinkaufszettelName"));
		
		$B = $gui->addSideButton("EAN\nprüfen", "lieferschein");
		$B->popup("", "EAN prüfen", "mEinkaufszettel", "-1", "checkEANPopup");
		
		return $gui->getBrowserHTML($id);
	}
	
	public function checkEANPopup(){
		$F = new HTMLInput("EAN", "text", "");
		$F->onEnter(OnEvent::rme($this, "checkEAN", array("this.value"), "function(transport){ \$j('#EANResult').html(transport.responseText); }"));
		echo $F."<pre id=\"EANResult\"></pre>";
	}
	
	public function checkEAN($EAN){
		$OEAN = new barcoo();
		$artikel = $OEAN->startSeach($EAN);
		
		print_r($artikel);
	}
	
	public function addEAN($EAN, $echo = true){
		$OEAN = new barcoo();
		$artikel = $OEAN->startSeach(trim($EAN));
		
		$fullname = "";
		$name = "";
		if(isset($artikel["fullname"]))
			$fullname = $name = $artikel["fullname"];
		
		if(isset($artikel["name"]) AND $artikel["name"] != "")
			$name = $artikel["name"];
		
		if($name != ""){
			$F = new Factory("Einkaufszettel");
			$F->sA("EinkaufszettelName", $name);
			$F->sA("EinkaufszettelBought", "0");
			$F->sA("EinkaufszettelMenge", "1");
			$O = $F->exists(true);
			if(!$O){
				$F->sA("EinkaufszettelEAN", $EAN);
				$F->sA("EinkaufszettelTime", time());
				if($fullname != $name)
					$F->sA("EinkaufszettelNameDetails", $fullname);
				$F->store();
			} else {
				$O->changeA("EinkaufszettelMenge", $O->A("EinkaufszettelMenge") + 1);
				$O->saveMe();
			}
		}
		
		if($echo)
			echo $this->getOverviewListEntry($name, time());
	}
	
	public function addItem($name, $overviewList = false){
		if(preg_match("/[0-9]+/", $name)){
			$this->addEAN($name, false);
		} elseif(trim($name) != ""){
			$F = new Factory("Einkaufszettel");
			$F->sA("EinkaufszettelName", $name);
			$F->sA("EinkaufszettelTime", time());
			$F->sA("EinkaufszettelMenge", "1");
			$F->store();
		}
		
		if(!$overviewList)
			echo $this->getListTable();
		else
			echo $this->getOverviewList();
	}
	
	public function reAddItem($EinkaufszettelID, $overviewList = false){
		$E = new Einkaufszettel($EinkaufszettelID);
		
		$F = new Factory("Einkaufszettel");
		$F->sA("EinkaufszettelBought", "0");
		$F->sA("EinkaufszettelName", $E->A("EinkaufszettelName"));
		$exists  = $F->exists(true);
		if($exists !== false){
			$exists->changeA("EinkaufszettelMenge", $exists->A("EinkaufszettelMenge") + 1);
			$exists->saveMe();
		} else {
			$E->changeA("EinkaufszettelBought", "0");
			$E->changeA("EinkaufszettelTime", time());
			$E->changeA("EinkaufszettelMenge", "1");
			$E->newMe();
		}
		
		#if(!$overviewList)
			echo $this->getListTable();
		#else
		#	echo $this->getOverviewList();
	}
	
	function deleteReAddItem($EinkaufszettelID){
		$E = new Einkaufszettel($EinkaufszettelID);
		
		$AC = anyC::get("Einkaufszettel", "EinkaufszettelName", $E->A("EinkaufszettelName"));
		$AC->addAssocV3("EinkaufszettelNameDetails", "=", $E->A("EinkaufszettelNameDetails"));
		while($E = $AC->getNextEntry())
			$E->deleteMe();
		
		echo $this->getListReAddTable();
	}
	
	public function getOverviewList(){
		$html = "";
		$AC = anyC::get("Einkaufszettel", "EinkaufszettelBought", "0");
		$AC->addOrderV3("EinkaufszettelTime", "DESC");
		$AC->setLimitV3("5");
		while($E = $AC->getNextEntry())
			$html .= $this->getOverviewListEntry($E->A("EinkaufszettelName"), $E->A("EinkaufszettelTime"));
		
		if($AC->numLoaded() == 0)
			$html .= "<div class=\"emptyElement\" style=\"padding-bottom:5px;\"><span style=\"color:grey;\">Der Einkaufszettel ist leer</span></div>";
		
		return $html;
	}
	
	public function getOverviewListEntry($name, $time){
		return "<div style=\"padding-bottom:5px;\"><small style=\"float:right;color:grey;\">".Util::CLDateParser($time)."</small>".($name != "" ? $name : "Artikel $EAN nicht gefunden!")."</div>";
	}

	public function getOverviewContent(){
		$html = "<div class=\"touchHeader\"><span class=\"lastUpdate\" id=\"lastUpdatemEinkaufszettelGUI\"></span><p>Einkaufen</p></div>
			<div style=\"padding:10px;\">";

		$I = new HTMLInput("EinkaufslisteNewEntryOV", "textarea");
		$I->placeholder("Neuer Eintrag");
		$I->style("width:100px;padding:3px;font-size:20px;font-family:monospace;height:32px;max-height:32px;");
		$I->onfocus("fheOverview.noreload.push('mEinkaufszettelGUI::getOverviewContent'); fheOverview.noresize = true;");
		$I->onblur("fheOverview.noreload.pop(); fheOverview.noresize = false;");
		#$I->onkeyup("var currentContent = \$j(this).val(); ".OnEvent::rme($this, "getACData", array("this.value"), "function(transport){ var json = jQuery.parseJSON(transport.responseText); if(json.length >= 1) \$j('#EinkaufslisteNewEntryAC').html(json[0].EinkaufszettelName.replace(currentContent, '<span style=\'color:white;\'>'+currentContent+'</span>')); else \$j('#EinkaufslisteNewEntryAC').html(''); }"));
		$I->onEnter(OnEvent::rme($this, "addItem", array("this.value", "1"), "function(transport){ \$j('#EinkaufszettelLastAdded').html(transport.responseText); }")." \$j(this).val('');");
		
		
		$B = new Button("Einkaufsliste anzeigen", "list", "touch");
		$B->popup("", "Einkaufsliste", "mEinkaufszettel", "-1", "showCurrentList", "", "", "{top:20, width:800, hPosition:'center', blackout:true}");
		
		
		$html .= "<div id=\"EinkaufslisteNewEntryContainer\">$B</div>
			<!--<div id=\"EinkaufszettelLastAdded\">-->";
		
		#$html .= $this->getOverviewList();
		
		$html .= "<!--</div>--></div>".OnEvent::script("\$j('[name=EinkaufslisteNewEntryOV]').css('width', (\$j('#EinkaufslisteNewEntryContainer').innerWidth() - 70)+'px');");
		echo $html;
	}
	
	public function showCurrentList(){
		
		$BM = new Button("Handy-Version\nanzeigen", "./fheME/Einkaufszettel/mobile.png");
		$BM->style("float:right;margin:10px;");
		$BM->onclick("window.open('".str_replace("/interface/rme.php", "/ubiquitous/CustomerPage/?CC=Shopping&key=".substr(Util::eK(), 0, 5), $_SERVER["SCRIPT_NAME"])."');");
		
		
		#$BH = new Button("Hinzufügen", "bestaetigung", "icon");
		#$BH->style("margin-left:10px;margin-top:10px;float:left;margin-top:-28px;");
		#$BH->onclick("if(\$j('input[name=EinkaufslisteNewEntry]').val() != 'Neuer Eintrag') ".OnEvent::rme($this, "addItem", array("\$j('input[name=EinkaufslisteNewEntry]').val()"), OnEvent::reloadPopup("mEinkaufszettel")));
		
		
		$I = new HTMLInput("EinkaufslisteNewEntry", "textarea", "");
		$I->placeholder("Neuer Eintrag");
		$I->style("width:390px;padding:5px;margin-left:5px;font-size:20px;float:left;font-family:monospace;max-width:390px;resize:none;height:35px;max-height:35px;margin-top:-45px;");
		#$I->onfocus("if(this.value == 'Neuer Eintrag') { \$j(this).val('').css('color', 'black'); }");
		#$I->onblur("if(this.value == '') { \$j(this).val('Neuer Eintrag').css('color', 'grey'); }");
		#$I->onkeyup("var currentContent = \$j(this).val(); ".OnEvent::rme($this, "getACData", array("this.value"), "function(transport){ var json = jQuery.parseJSON(transport.responseText); if(json.length >= 1) \$j('#EinkaufslisteNewEntryAC').html(json[0].EinkaufszettelName.replace(currentContent, '<span style=\'color:white;\'>'+currentContent+'</span>')); else \$j('#EinkaufslisteNewEntryAC').html(''); }"));
		$I->onEnter(OnEvent::rme($this, "addItem", array("this.value"), "function(transport){ \$j('#currentList').html(transport.responseText); }")." \$j(this).val('');");
		
		
		
		$B = new Button("Liste schließen", "stop");
		$B->onclick(OnEvent::closePopup("mEinkaufszettel"));
		$B->style("float:right;margin:10px;");
		
		
		echo "
		<div style=\"width:400px;float:right;\">
			$B$BM
			<div id=\"EinkaufslisteNewEntryAC\" style=\"width:390px;height:35px;padding:5px;font-size:20px;margin-left:6px;margin-top:3px;font-family:monospace;color:grey;float:left;\"></div>
			".$I."
			<div style=\"clear:both;\"></div>
			<div id=\"currentList\">".$this->getListTable()."</div>
		</div>
		<div style=\"width:400px;\" id=\"reAddList\">
			".$this->getListReAddTable()."
		</div>
		<div style=\"clear:both;\"></div>
			".OnEvent::script("setTimeout(function(){ \$j('input[name=EinkaufslisteNewEntry]').focus(); }, 200);");
	}
	
	private function getListReAddTable(){
		#$TB = new HTMLTable(2);
		#$TB->weight("lightColored");
		#$TB->maxHeight(400);
		#$TB->useForSelection();
		
		$AC = anyC::get("Einkaufszettel", "EinkaufszettelBought", "1");
		$AC->setFieldsV3(array("EinkaufszettelName", "EinkaufszettelNameDetails", "MAX(EinkaufszettelID) AS maxID"));
		$AC->addAssocV3("EinkaufszettelBoughtTime", ">", time() - 3600 * 24 * 60);
		$AC->addAssocV3("EinkaufszettelEAN", "=", "");
		$AC->addGroupV3("EinkaufszettelName");
		$AC->addOrderV3("EinkaufszettelName");
		#$AC->addGroupV3("EinkaufszettelNameDetails");
		
		$L = new HTMLList();
		$L->noDots();
		$L->addListStyle("padding-top:10px;width:380px;padding-left:0px;");
		
		while($B = $AC->getNextEntry()){
			#$BT = new Button("Eintrag löschen", "trash_stroke", "iconicL");
			#$BT->rmePCR("mEinkaufszettel", "-1", "deleteReAddItem", $B->A("maxID"), "function(transport){ \$j('#reAddList').html(transport.responseText); }");
			#$BT->style("float:left;margin-right:10px;margin-top:-4px;");
			
			$L->addItem($B->A("EinkaufszettelName").($B->A("EinkaufszettelNameDetails") != "" ? "<br /><small style=\"color:grey;\">".$B->A("EinkaufszettelNameDetails")."</small>" : ""));
			$L->addItemStyle("margin-left:5px;height:24px;white-space:nowrap;font-size:20px;padding-left:10px;padding-top:10px;padding-bottom:10px;margin-top:0px;cursor:move;-webkit-touch-callout: none;-webkit-user-select: none;-khtml-user-select: none;-moz-user-select: none;-ms-user-select: none;user-select: none;");
			$L->addItemClass("swipe");
			$L->addItemData("maxid", $B->A("maxID"));
			#$TB->addRow(array($BT, ));
			#$TB->addRowStyle("font-size:20px;");
			#$TB->addCellClass(2, "swipe");
			
			#$TB->addCellEvent(2, "click", OnEvent::rme($this, "reAddItem", $B->A("maxID"), "function(transport){ \$j('#currentList').html(transport.responseText); }"));
		}
		
		return $L.OnEvent::script("
			\$j('#reAddList ul').parent().css('height', contentManager.maxHeight()).css('overflow', 'auto');
			//var scrollStartedAt = null;
			\$j('.swipe').hammer().on('touch release dragright', function(event){
				//event.gesture.preventDefault();
				
				
				/*if(event.type == 'dragdown'){
					\$j('#reAddList').scrollTop(-event.gesture.deltaY);
				}
				if(event.type == 'dragup'){
					\$j('#reAddList').scrollTop(-event.gesture.deltaY);
				}*/

				if(event.type == 'touch'){
					scrollStartedAt = \$j('#reAddList').scrollTop();
					
					\$j(this).addClass('highlight');
					return;
				}
				
				if(event.type == 'release'){
					event.gesture.preventDefault();
					
					if(event.gesture.deltaX > 150)
						".OnEvent::rme($this, "reAddItem", array("\$j(this).data('maxid')"), "function(transport){ \$j('#currentList').html(transport.responseText); }")."
					
					\$j(this).removeClass('confirm');
					\$j(this).removeClass('highlight');
					\$j(this).animate({'margin-left': 5});
					return;
				}
				
				if(event.type == 'dragright'){
					event.gesture.preventDefault();
					//\$j('#logger').html(event.gesture.deltaX);
					var margin = event.gesture.deltaX;

					if(margin >= 150)
						\$j(this).addClass('confirm');
						
					if(margin < 150)
						\$j(this).removeClass('confirm');

					if(margin > 250)
						margin = 250;
						
					\$j(this).css('margin-left', margin);
				}
			});
					
				");
	}
	
	public function getACData($query){
		if($query == "")
			die("[]");
		
		$AC = anyC::get("Einkaufszettel");
		$AC->addAssocV3("EinkaufszettelName", "LIKE", "$query%");
		$AC->setLimitV3(1);
		
		echo $AC->asJSON();
	}
	
	public function getListTable(){
		$T = new HTMLTable(2, "Einkaufsliste");
		$T->maxHeight(400);
		$T->setColWidth(2, 30);
		$T->weight("light");
		$T->useForSelection(false);
		
		$AC = anyC::get("Einkaufszettel", "EinkaufszettelBought", "0");
		$AC->addOrderV3("EinkaufszettelTime", "DESC");
		
		while($E = $AC->getNextEntry()){
			$BT = new Button("Löschen", "trash_stroke", "iconicL");
			$BT->onclick(OnEvent::rme($E, "deleteMe", "", OnEvent::reloadPopup("mEinkaufszettel")));
			
			$T->addRow(array(($E->A("EinkaufszettelMenge") > 1 ? $E->A("EinkaufszettelMenge")." x " : "").$E->A("EinkaufszettelName").($E->A("EinkaufszettelNameDetails") != "" ? "<br /><small style=\"color:grey;\">".$E->A("EinkaufszettelNameDetails")."</small>" : ""), $BT));
			$T->addRowStyle("font-size:20px;");
			#$T->addCellEvent(1, "click", OnEvent::rme($this, "boughtItem", $E->getID(), "function(transport){ \$j('#currentList').html(transport.responseText); }"));
			
		}
		
		if($AC->numLoaded() == 0){
			$T->addRow(array("Die Einkaufsliste enthält keine Einträge."));
			$T->addRowColspan(1, 2);
		}
		
		return $T;
	}
	
	public function boughtItem($EinkaufszettelID){
		$E = new Einkaufszettel($EinkaufszettelID);
		$E->setBought();
		
		echo $this->getListTable();
	}
	
	public static function getOverviewPlugin(){
		$P = new overviewPlugin("mEinkaufszettelGUI", "Einkaufen", 0);
		#$P->updateInterval(300);
		
		return $P;
	}
}
?>