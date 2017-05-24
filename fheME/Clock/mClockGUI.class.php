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
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 *  2007 - 2017, Furtmeier Hard- und Software - Support@Furtmeier.IT
 */

class mClockGUI extends UnpersistentClass implements iGUIHTMLMP2 {

	public function getHTML($id, $page){
		
	}

	public function getOverviewContent(){
		$html = "<div class=\"touchHeader\"><p>Uhr</p></div>
			<div style=\"padding:10px;\">";

		$html .= "<div id=\"fheOverviewClock\"><span>".Util::CLWeekdayName(date("w"))."<br><b>".date("d").". ".Util::CLMonthName(date("m"))." ".date("Y")."</b></span><b>".Util::CLTimeParser(time())."</b></div>";
		
		$html .= "</div>";
		echo $html;
	}
	
	public static function getOverviewPlugin(){
		$P = new overviewPlugin("mClockGUI", "Uhr", 100);
		
		$P->updateInterval(1);
		$P->updateFunction("function(){ var jetzt = new Date(); $('fheOverviewClock').update('<span>'+fheOverview.days[jetzt.getDay()]+'<br><b>'+jetzt.getDate()+'. '+fheOverview.months[jetzt.getMonth()]+' '+jetzt.getFullYear()+'</b></span><b>'+jetzt.getHours()+':'+(jetzt.getMinutes() < 10 ? '0' : '')+jetzt.getMinutes()+'</b>'); }");
		
		return $P;
	}


}
?>