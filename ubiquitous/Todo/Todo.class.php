<?php
/*
 *  This file is part of phynx.

 *  phynx is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.

 *  phynx is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 *  2007 - 2012, Rainer Furtmeier - Rainer@Furtmeier.de
 */
class Todo extends PersistentObject {
	public static $repeatTypes = array("" => "nicht Wiederholen", "weekly" => "Wöchentlich", "monthly" => "Monatlich", "yearly" => "Jährlich");
	/*public function invitePerson($id, $TeamId = 0, $nooutput = false){
		return mTeilnehmerGUI::invitePerson("Todo", $this->ID, $id, $TeamId, $nooutput);
	}
	
	public function inviteTeam($id){
		list($anz, $ges) = mTeilnehmerGUI::inviteTeam("Todo", $this->ID, $id);
		echo "message:EventMessages.M002('$anz/$ges')";
	}*/
	private $updateGoogle = true;

	public function updateGoogle($b){
		$this->updateGoogle = $b;
	}
	
	public function saveMe($checkUserData = true, $output = false) {
		$old = new Todo($this->getID());
		$old->loadMe();
		$fromDay = date("Y-m-d", Util::CLDateParser($this->A("TodoFromDay"), "store"));
		$fromTime = Util::formatTime("de_DE", Util::CLTimeParser($this->A("TodoFromTime"), "store"));
		#die($this->getID());
		
		$this->changeA("TodoLastChange", time());
		
		#$name = $this->getOwnerObject()->getCalendarTitle();
		
		if($this->A("TodoAllDay")){
			$this->changeA ("TodoFromTime", Util::CLTimeParser(0));
			$this->changeA ("TodoTillTime", Util::CLTimeParser(0));
		}
		
		parent::saveMe($checkUserData, false);
		
		if(Session::isPluginLoaded("mGoogle") AND $this->updateGoogle){
			$KE = mTodoGUI::getCalendarDetails("Todo", $this->getID());
			if($this->A("TodoUserID") == Session::currentUser()->getID())
				if($old->A("TodoUserID") == $this->A("TodoUserID"))
					Google::calendarUpdateEvent(mTodoGUI::getCalendarDetails("Todo", $this->getID()));#"Todo", $this->getID(), $name, $this->A("TodoDescription"), $this->A("TodoLocation"), $fromDay, $fromTime, date("Y-m-d", Util::CLDateParser($this->A("TodoTillDay"), "store")), Util::formatTime("de_DE", Util::CLTimeParser($this->A("TodoTillTime"), "store") + 3600));
				else {
					Google::calendarDeleteEvent($KE);#"Todo", $this->getID());
					Google::calendarCreateEvent($KE);
				}
			else {
				Google::calendarDeleteEvent($KE);#"Todo", $this->getID());
				Google::calendarCreateEvent($KE, $this->A("TodoUserID"));
			}
		}
		
		
	}

	public function getOwnerObject(){
		$c = $this->A("TodoClass")."GUI";
		if($c == "KalenderGUI")
			$O = $this;
		else{
			try {
				$O = new $c($this->A("TodoClassID"));
			} catch (ClassNotFoundException $e){
				$O = $this;	
			}
		}
		return $O;
	}

	public function newMe($checkUserData = true, $output = false) {
		$this->changeA("TodoLastChange", time());
		
		#if($this->A("TodoGUID") == "")
		#	$this->changeA("TodoGUID", uniqid()."-".uniqid());
		
		$id = parent::newMe($checkUserData, false);
		
		if(Session::isPluginLoaded("mSync") AND ($this->A("TodoExceptionForID") == "0" OR $this->A("TodoExceptionForID") == ""))
			mSync::newGUID("Todo", $id);
		
		#$name = $this->getOwnerObject()->getCalendarTitle();

		if(Session::isPluginLoaded("mGoogle") AND $this->updateGoogle)
			if($this->A("TodoUserID") == Session::currentUser()->getID())
				Google::calendarCreateEvent(mTodoGUI::getCalendarDetails("Todo", $id));

		if($this->A("TodoClass") == "DBMail" AND Session::isPluginLoaded("mMail")){
			$M = new Mail(-1);
			$M->assignMail("Todo", $id, $this->A("TodoClassID"));
		}
			
		return $id;
	}

	public function deleteMe() {
		if(Session::isPluginLoaded("mGoogle"))
			Google::calendarDeleteEvent(mTodoGUI::getCalendarDetails("Todo", $this->getID()));
		
		$AC = anyC::get("Todo", "TodoExceptionForID", $this->getID());
		while($T = $AC->getNextEntry())
			$T->deleteMe();
		
		if($this->A("TodoClass") == "DBMail" AND Session::isPluginLoaded("mMail")){
			$M = new Mail(-1);
			$M->revokeAssignMail("Todo", $this->getID(), $this->A("TodoClassID"));
		}
		
		parent::deleteMe();
	}

	public function addFile($id){
		mFileGUI::addFile("Todo",$this->ID, $id);
		echo "message:EventMessages.M004";
	}

	public function getCalendarTitle(){
		return trim($this->A("TodoName"));
	}
}
?>
