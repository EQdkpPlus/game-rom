<?php
/*	Project:	EQdkp-Plus
 *	Package:	Runes of magic game package
 *	Link:		http://eqdkp-plus.eu
 *
 *	Copyright (C) 2006-2015 EQdkp-Plus Developer Team
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU Affero General Public License as published
 *	by the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU Affero General Public License for more details.
 *
 *	You should have received a copy of the GNU Affero General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if(!class_exists('blasc')) {
	class blasc extends itt_parser{
		
		public static $shortcuts = array('puf' => 'urlfetcher', 'pfh' => array('file_handler', array('infotooltips')));

		public $supported_games = array('rom');
		public $av_langs = array('en' => 'en_US', 'de' => 'de_DE', 'ru' => 'ru_RU');#, 'fr' => 'fr_FR', 'jp' => 'ja_JP'); currently not supported by our aion-game-folder

		public $settings = array(
			'itt_icon_loc' => array('name' => 'itt_icon_loc',
									'language' => 'pk_itt_icon_loc',
									'fieldtype' => 'text',
									'size' => false,
									'options' => false,
									'default' => 'http://romdata.buffed.de/img/icons/rom/64/'),
			'itt_icon_ext' => array('name' => 'itt_icon_ext',
									'language' => 'pk_itt_icon_ext',
									'fieldtype' => 'text',
									'size' => false,
									'options' => false,
									'default' => '.png'),
			'itt_default_icon' => array('name' => 'itt_default_icon',
										'language' => 'pk_itt_default_icon',
										'fieldtype' => 'text',
										'size' => false,
										'options' => false,
										'default' => 'not_yet_found'),
			'itt_useitemlist' => array('name' => 'itt_useitemlist',
										'language' => 'pk_itt_useitemlist',
										'fieldtype' => 'checkbox',
										'size' => false,
										'options' => false,
										'default' => 1)
								);

		public $itemlist = array();

		private $searched_langs = array();

		protected function u_construct() {}

		protected function u_destruct(){
			unset($this->itemlist);
			unset($this->searched_langs);
		}

		//initializes the item/recipelist. if it does not exists in the cache, get it from: http://www.aiondatabase.com/xml/en_US/items(recipes)/item(recipe)list.xml
		private function getItemlist($lang, $forceupdate=false, $type='item'){
			$url = ($lang == 'en') ? 'getbuffed.com' : 'buffed.'.$lang;
			$this->{$type.'list'} = unserialize(file_get_contents($this->pfh->FilePath($this->config['game'].'_'.$lang.'_'.$type.'list.itt', 'itt_cache')));
			if(!$this->itemlist OR $forceupdate)
			{
				$urlitemlist = $this->puf->fetch('http://www.'.$url.'/tooltiprom/items/xml/itemlist.xml');
				$xml = simplexml_load_string($urlitemlist);
				if(is_object($xml)) {
					foreach($xml->children() as $item) {
						$name = (string) $item->attributes('name_'.str_replace('_','',$this->av_langs[$lang]));
						$this->{$type.'list'}[(int)$item->attributes('id')][$lang] = $name;
					}
				}
				$this->cache->putContent($this->pfh->FilePath($this->config['game'].'_'.$lang.'_'.$type.'list.itt', 'itt_cache'), serialize($this->{$type.'list'}));
			}
			return true;
		}

		private function getItemIDfromItemlist($itemname, $lang, $forceupdate=false, $searchagain=0, $type='item'){
			$searchagain++;
			$this->getItemlist($lang,$forceupdate,$type);
			$item_id = array(0,0);

			//search in the itemlist for the name
			$loaded_item_langs = array();
			if($type == 'item') {
				foreach($this->itemlist as $itemID => $iteml){
					foreach($iteml as $slang => $name) {
						$loaded_item_langs[] = $slang;
						if($itemname == $name){
							$item_id[0] = $itemID;
							$item_id[1] = 'items';
							break 2;
						}
					}
				}
			}
			if(!$item_id[0] AND count($this->av_langs) > $searchagain) {
				$toload = array();
				foreach($this->av_langs as $c_lang => $langlong) {
					if(!in_array($c_lang,$loaded_item_langs)) {
						$toload[$c_lang][] = 'item';
					}
				}
				foreach($toload as $lang => $load) {
					foreach($load as $type) {
						$item_id = $this->getItemIDfromItemlist($itemname, $lang, true, $searchagain, $type);
						if($item_id[0]) {
							break 2;
						}
					}
				}
			}
			return $item_id;
		}

		private function getItemIDfromUrl($itemname, $lang, $searchagain=0){
			$searchagain++;
			$codedname = str_replace(' ', '%2B', $itemname);

			/*var bt_icons = {"211475":"shop_female__blade_mall007"};
	var bt = new Btabs([{"id":"items","rows":[{"id":211475,"n":"Cowboyklinge","q":8,"l":1,"cl":"Waffe","scl":"Schwert - Einhand","viewable":"3,211475"}],"n":"Gegenst\u00e4nde","tpl":"rom_itemlist"}]);bt.init();*/
	
			$url = ($lang == 'en') ? 'getbuffed.com' : 'buffed.'.$lang;
			$data = $this->puf->fetch('http://romdata.'.$url.'/?f='. $codedname);
			$this->searched_langs[] = $lang;
			if (preg_match_all('#new Btabs\(\[\{\"id\":\"items\",\"rows\":\[(.*?)\],\"n\":\"(.*?)\",\"tpl\":\"rom_itemlist\"\}#', $data, $matchs)){
				if (preg_match_all('#\{\"id\":([0-9]*),\"n\":\"(.*?)\",\"q\":(.*?)\"\}#', $matchs[1][0], $matches)){
					foreach ($matches[0] as $key => $match)
					{
						$item_name_tosearch = html_entity_decode($matches[2][$key]);

						if (strcasecmp($item_name_tosearch, $itemname) == 0)
						{
							// Extract the item's ID from the match.
							$item_id[0] = $matches[1][$key];
							$item_id[1] = 'item';
							break;
						}
					}
				}
			}
			if(!$item_id AND count($this->av_langs) > $searchagain) {
				foreach($this->av_langs as $c_lang => $langlong) {
					if(!in_array($c_lang,$this->searched_langs)) {
						$item_id = $this->getItemIDfromUrl($itemname, $c_lang, $searchagain);
					}
					if($item_id[0]) {
						break;
					}
				}
			}
			return $item_id;
		}

		protected function searchItemID($itemname, $lang){
			if($this->config['useitemlist']) {
				return $this->getItemIDfromItemlist($itemname, $lang);
			} else {
				return $this->getItemIDfromUrl($itemname, $lang);
			}
		}

		protected function getItemData($item_id, $lang, $itemname='', $type='items'){
			settype($item_id, 'int');
			$item = array('id' => $item_id);
			if(!$item_id) return null;
			$url = ($lang == 'en') ? 'getbuffed.com' : 'buffed.'.$lang;
			$item['link'] = 'http://romdata.'.$url.'/tooltiprom/items/xml/'.$item_id.'.xml';
			//get the xml from blasc: http://www.buffed.de/tooltiprom/items/xml/$itemid.xml
			$itemxml = $this->puf->fetch($item['link'], array('Cookie: cookieLangId="'.$lang.'";'));
			$xml = simplexml_load_string($itemxml);
			$item['name'] = (strlen($itemname) > 1) ? $itemname : (string) $xml->Name;
	
			//build itemhtml
			$xml_tt = (string) $xml->display_html;
			$html = "<table class='db-tooltip' cellspacing='0'><tr><td class='normal'>";
			$html .= str_replace('"', "'", $xml_tt);
			$html .= "</td><td class='right'></td></tr><tr><td class='bottomleft'></td><td class='bottomright'></td></table>";
	
			$item['html'] = $html;
			$item['lang'] = (string) $xml->Locale;
			$item['icon'] = (string) $xml->Icon;
			$item['color'] = 'q'.((int) $xml->Quality);
			return $item;
		}
	}
}
?>