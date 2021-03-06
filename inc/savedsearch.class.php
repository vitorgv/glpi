<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2017 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Saved searches class
**/
class SavedSearch extends CommonDBTM {

   static $rightname               = 'bookmark_public';

   const WIDTH  = 750;
   const SEARCH = 1; //SEARCH SYSTEM bookmark
   const URI    = 2;
   const ALERT  = 3; //SEARCH SYSTEM search alert

   const COUNT_NO = 0;
   const COUNT_YES = 1;
   const COUNT_AUTO = 2;

   public static function getTypeName($nb = 0) {
      return _n('Saved search', 'Saved searches', $nb);
   }

   function getForbiddenStandardMassiveAction() {
      $forbidden   = parent::getForbiddenStandardMassiveAction();
      if (!Session::haveRight('config', UPDATE)) {
         $forbidden[] = 'update';
      }
      return $forbidden;
   }


   function getSpecificMassiveActions($checkitem=NULL) {
      $actions[get_called_class().MassiveAction::CLASS_ACTION_SEPARATOR.'unset_default'] = __('Unset as default');
      $actions[get_called_class().MassiveAction::CLASS_ACTION_SEPARATOR.'change_count_method'] = __('Change count method');
      return $actions;
   }


   static function showMassiveActionsSubForm(MassiveAction $ma) {
      switch ($ma->getAction()) {
         case 'change_count_method':
            $values = [
               self::COUNT_AUTO  => _('Auto'),
               self::COUNT_YES   => __('Yes'),
               self::COUNT_NO    => __('No')
            ];
            Dropdown::showFromArray('do_count', $values, array('width' => '20%'));
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }


   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case 'unset_default' :
            $input = $ma->getInput();
            if ($item->unmarkDefaults($ids)) {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
            } else {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            return;
            break;
         case 'change_count_method':
            $input = $ma->getInput();
            if ($item->setDoCount($ids, $input['do_count'])) {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_OK);
            } else {
               $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
            }
            break;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }


   function canCreateItem() {
      if ($this->fields['is_private'] == 1) {
         return (Session::haveRight('config', CREATE)
           || $this->fields['users_id'] == Session::getLoginUserID());
      }
      return parent::canCreateItem();
   }


   function canViewItem() {
      if ($this->fields['is_private'] == 1) {
         return (Session::haveRight('config', READ)
            || $this->fields['users_id'] == Session::getLoginUserID());
      }
      return parent::canViewItem();
   }


   function isNewItem() {
      /// For tabs management : force isNewItem
      return false;
   }


   function defineTabs($options=array()) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      return $ong;
   }


   function getSearchOptionsNew() {
      global $CFG_GLPI;

      $tab = [];

      $tab[] = [
         'id'                 => 'common',
         'name'               => __('Characteristics')
      ];

      $tab[] = [
         'id'                 => '1',
         'table'              => $this->getTable(),
         'field'              => 'name',
         'name'               => __('Name'),
         'datatype'           => 'itemlink',
         'massiveaction'      => false // implicit key==1
      ];

      $tab[] = [
         'id'                 => '2',
         'table'              => $this->getTable(),
         'field'              => 'id',
         'name'               => __('ID'),
         'massiveaction'      => false, // implicit field is id
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => 3,
         'table'              => User::getTable(),
         'field'              => 'name',
         'name'               => __('User'),
         'datatype'           => 'dropdown'
      ];

      $tab[] = [
         'id'                 => '8',
         'table'              => $this->getTable(),
         'field'              => 'itemtype',
         'name'               => __('Item type'),
         'massiveaction'      => false,
         'datatype'           => 'itemtypename',
         'types'              => self::getUsedItemtypes()
      ];

      $tab[] = [
         'id'                 => 9,
         'table'              => $this->getTable(),
         'field'              => 'last_execution_time',
         'name'               => _('Last duration (ms)'),
         'massiveaction'      => false,
         'datatype'           => 'number'
      ];

      $tab[] = [
         'id'                 => 10,
         'table'              => $this->getTable(),
         'field'              => 'do_count',
         'name'               => _('Count'),
         'massiveaction'      => true,
         'datatype'           => 'specific',
         'searchtype'         => 'equals'
      ];

      $tab[] = [
         'id'                 => 11,
         'table'              => SavedSearch_User::getTable(),
         'field'              => 'users_id',
         'name'               => __('Default'),
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child'
         ],
         'datatype'           => 'specific',
         'searchtype'         => [
            0 => 'equals',
            1 => 'notequals'
         ]
      ];

      return $tab;
   }

   function prepareInputForAdd($input) {

      if (!isset($input['url']) || !isset($input['type'])) {
         return false;
      }

      $taburl = parse_url(rawurldecode($input['url']));

      $index  = strpos($taburl["path"], "plugins");
      if (!$index) {
         $index = strpos($taburl["path"], "front");
      }
      $input['path'] = Toolbox::substr(
         $taburl["path"],
         $index,
         Toolbox::strlen($taburl["path"]) - $index
      );

      $query_tab = array();

      if (isset($taburl["query"])) {
         parse_str($taburl["query"], $query_tab);
      }

      $input['query'] = Toolbox::append_params(
         $this->prepareQueryToStore($input['type'],
         $query_tab)
      );

      return $input;
   }


   function pre_updateInDB() {

      // Set new user if initial user have been deleted
      if (($this->fields['users_id'] == 0)
          && $uid=Session::getLoginUserID()) {
         $this->input['users_id']  = $uid;
         $this->fields['users_id'] = $uid;
         $this->updates[]          = "users_id";
      }
   }


   function post_getEmpty() {

      $this->fields["users_id"]     = Session::getLoginUserID();
      $this->fields["is_private"]   = 1;
      $this->fields["is_recursive"] = 0;
      $this->fields["entities_id"]  = $_SESSION["glpiactive_entity"];
   }


   function cleanDBonPurge() {
      global $DB;

      $query="DELETE
              FROM `glpi_savedsearches_users`
              WHERE `savedsearches_id` = '".$this->fields['id']."'";
      $DB->query($query);
   }


   /**
    * Print the saved search form
    *
    * @param integer $ID      ID of the item
    * @param array   $options Options:
    *     - target for the Form
    *     - type when adding
    *     - url when adding
    *     - itemtype when adding
    *
    * @return void
   **/
   function showForm($ID, $options=array()) {
      $ID = $this->getID();

      // Only an edit form : always check w right
      if ($ID > 0) {
         $this->check($ID, UPDATE);
      } else {
         $this->check(-1, CREATE);
      }

      echo "<form method='post' name='form_save_query' action='".$this->getFormURL()."'>";
      echo "<div class='center'>";
      if (isset($options['itemtype'])) {
         echo "<input type='hidden' name='itemtype' value='".$options['itemtype']."'/>";
      }

      if (isset($options['type']) && ($options['type'] != 0)) {
         echo "<input type='hidden' name='type' value='".$options['type']."'>";
      }

      if (isset($options['url'])) {
         echo "<input type='hidden' name='url' value='" . rawurlencode($options['url']) . "'/>";
      }

      echo "<table class='tab_cadre' width='".self::WIDTH."px'>";
      echo "<tr><th colspan='2'>";
      if ($ID > 0) {
         //TRANS: %1$s is the Itemtype name and $2$d the ID of the item
         printf(__('%1$s - ID %2$d'), $this->getTypeName(1), $ID);
      } else {
         echo __('New item');
      }
      echo "</th></tr>";

      echo "<tr><td class='tab_bg_1'>".__('Name')."</td>";
      echo "<td class='tab_bg_1'>";
      Html::autocompletionTextField($this, "name", array('user' => $this->fields["users_id"]));
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'><td>".__('Visibility')."</td>";
      echo "<td>";

      if ($this->canCreate()) {
         Dropdown::showPrivatePublicSwitch($this->fields["is_private"],
                                           $this->fields["entities_id"],
                                           $this->fields["is_recursive"]);
      } else {
         if ($this->fields["is_private"]) {
            echo __('Private');
         } else {
            echo __('Public');
         }
      }
      echo "</td></tr>";

      if ($ID <= 0) { // add
         echo "<tr>";
         echo "<td class='tab_bg_2 top' colspan='2'>";
         echo "<input type='hidden' name='users_id' value='".$this->fields['users_id']."'>";
         echo "<div class='center'>";
         echo "<input type='submit' name='add' value=\""._sx('button', 'Add')."\" class='submit'>";
         echo "</div></td></tr>";

      } else {
         echo "<tr>";
         echo "<td class='tab_bg_2 top' colspan='2'>";
         echo "<input type='hidden' name='id' value='$ID'>";
         echo "<input type='submit' name='update' value=\"".__s('Save')."\" class='submit'>";
         echo "</td></tr><tr><td class='tab_bg_2 right' colspan='2'>";
         echo "<input type='submit' name='purge' value=\""._sx('button', 'Delete permanently')."\"
                class='submit'>";
         echo "</td></tr>";
      }
      echo "</table>";

      if (isset($options['ajax'])) {
         $js = "$(function() {
            $('form[name=form_save_query]').submit(function (e) {
               e.preventDefault();
               var _this = $(this);
               $.ajax({
                  url: _this.attr('action').replace(/\/front\//, '/ajax/').replace(/\.form/, ''),
                  method: 'POST',
                  data: _this.serialize(),
                  success: function(res) {
                     if (res.success == true) {
                        savesearch.dialog('close');
                     }
                     displayAjaxMessageAfterRedirect();
                  }
               });
            });
         });";
         echo Html::scriptBlock($js);
      }
      echo "</div>";
      Html::closeForm();
   }


   /**
    * Prepare query to store depending of the type
    *
    * @param integer $type      Saved search type (self::SEARCH, self::URI or self::ALERT)
    * @param array   $query_tab Parameters
    *
    * @return clean query array
   **/
   protected function prepareQueryToStore($type, $query_tab) {

      switch ($type) {
         case self::SEARCH:
         case self::ALERT:
            $fields_toclean = [
               'add_search_count',
               'add_search_count2',
               'delete_search_count',
               'delete_search_count2',
               'start',
               '_glpi_csrf_token'
            ];
            foreach ($fields_toclean as $field) {
               if (isset($query_tab[$field])) {
                  unset($query_tab[$field]);
               }
            }

            break;
      }
      return $query_tab;
   }


   /**
    * Prepare query to use depending of the type
    *
    * @param integer $type      Saved search type (see SavedSearch constants)
    * @param array   $query_tab Parameters array
    *
    * @return prepared query array
   **/
   function prepareQueryToUse($type, $query_tab) {

      switch ($type) {
         case self::SEARCH:
         case self::ALERT:
            // Check if all datas are valid
            $opt = Search::getCleanedOptions($this->fields['itemtype']);

            $query_tab_save = $query_tab;
            $partial_load   = false;
            // Standard search
            if (isset($query_tab_save['criteria']) && count($query_tab_save['criteria'])) {
               unset($query_tab['criteria']);
               $new_key = 0;
               foreach ($query_tab_save['criteria'] as $key => $val) {
                  if (($val['field'] != 'view') && ($val['field'] != 'all')
                      && (!isset($opt[$val['field']])
                          || (isset($opt[$val['field']]['nosearch'])
                              && $opt[$val['field']]['nosearch']))) {
                     $partial_load = true;
                  } else {
                     $query_tab['criteria'][$new_key] = $val;
                     $new_key++;
                  }
               }
            }

            // Meta search
            if (isset($query_tab_save['metacriteria']) && count($query_tab_save['metacriteria'])) {
               $meta_ok = Search::getMetaItemtypeAvailable($query_tab['itemtype']);

               unset($query_tab['metacriteria']);

               $new_key = 0;
               foreach ($query_tab_save['metacriteria'] as $key => $val) {
                  if (isset($val['itemtype'])) {
                     $opt = Search::getCleanedOptions($val['itemtype']);
                  }
                  // Use if meta type is valid and option available
                  if (!isset($val['itemtype']) || !in_array($val['itemtype'], $meta_ok)
                      || !isset($opt[$val['field']])) {
                     $partial_load = true;
                  } else {
                     $query_tab['metacriteria'][$new_key] = $val;
                     $new_key++;
                  }
               }
            }

            // Display message
            if ($partial_load) {
               Session::addMessageAfterRedirect(__('Partial load of the saved search.'), false, ERROR);
            }
            // add reset value
            $query_tab['reset'] = 'reset';
            break;
      }
      return $query_tab;
   }


   /**
    * Load a saved search
    *
    * @param integer $ID ID of the saved search
    *
    * @return nothing
   **/
   function load($ID) {
      global $CFG_GLPI;

      if ($params = $this->getParameters($ID)) {
         $url  = $CFG_GLPI['root_doc']."/".rawurldecode($this->fields["path"]);
         $url .= "?".Toolbox::append_params($params);

         Html::redirect($url);
      }
   }


   /**
    * Get saved search parameters
    *
    * @param integer $ID ID of the saved search
    *
    * @return array|false
   **/
   function getParameters($ID) {
      if ($this->getFromDB($ID)) {
         $query_tab = array();
         parse_str($this->fields["query"], $query_tab);
         $query_tab['savedsearches_id'] = $ID;
         if (class_exists($this->fields['itemtype']) || $this->fields['itemtype'] == 'AllAssets') {
            return $this->prepareQueryToUse($this->fields["type"], $query_tab);
         }
      }
      return false;
   }

   /**
    * Mark saved search as default view for the current user
    *
    * @param integer $ID ID of the saved search
    *
    * @deprecated since version 9.2; @see SavedSearch::markDefault()
    *
    * @return void
   **/
   function mark_default($ID) {
      Toolbox::logDebug('mark_default() method is deprecated');
      return $this->markDefault($ID);
   }

   /**
    * Mark saved search as default view for the currect user
    *
    * @param integer $ID ID of the saved search
    *
    * @return void
   **/
   function markDefault($ID) {
      global $DB;

      if ($this->getFromDB($ID)
         && ($this->fields['type'] != self::URI)) {
         $dd = new SavedSearch_User();
         // Is default view for this itemtype already exists ?
         $query = "SELECT `id`
                   FROM `glpi_savedsearches_users`
                   WHERE `users_id` = '".Session::getLoginUserID()."'
                         AND `itemtype` = '".$this->fields['itemtype']."'";

         if ($result = $DB->query($query)) {
            if ($DB->numrows($result) > 0) {
               // already exists update it
               $updateID = $DB->result($result, 0, 0);
               $dd->update([
                  'id'                 => $updateID,
                  'savedsearches_id'   => $ID
               ]);
            } else {
               $dd->add([
                  'savedsearches_id'   => $ID,
                  'users_id'           => Session::getLoginUserID(),
                  'itemtype'           => $this->fields['itemtype']
               ]);
            }
         }
      }
   }


   /**
    * Mark savedsearch as default view for the current user
    *
    * @param integer $ID ID of the saved search
    *
    * @deprecated since version 9.2; @see SavedSearch::unmarkDefault()
    *
    * @return void
   **/
   function unmark_default($ID) {
      Toolbox::logDebug('unmark_default() method is deprecated');
      return $this->unmarkDefault($ID);
   }


   /**
    * Unmark savedsearch as default view for the current user
    *
    * @param integer $ID ID of the saved search
    *
    * @return void
   **/
   function unmarkDefault($ID) {
      global $DB;

      if ($this->getFromDB($ID)
         && ($this->fields['type'] != self::URI)) {
         $dd = new SavedSearch_User();
         // Is default view for this itemtype already exists ?
         $query = "SELECT `id`
                   FROM `glpi_savedsearches_users`
                   WHERE `users_id` = '".Session::getLoginUserID()."'
                         AND `savedsearches_id` = '$ID'
                         AND `itemtype` = '".$this->fields['itemtype']."'";

         if ($result = $DB->query($query)) {
            if ($DB->numrows($result) > 0) {
               // already exists delete it
               $deleteID = $DB->result($result, 0, 0);
               $dd->delete(['id' => $deleteID]);
            }
         }
      }
   }


   /**
    * Unmark savedsearch as default view
    *
    * @param array $ids IDs of the saved searches
    *
    * @return boolean
   **/
   function unmarkDefaults(array $ids) {
      global $DB;

      if (Session::haveRight('config', UPDATE)) {
         $query = "DELETE FROM `glpi_savedsearches_users`
                     WHERE `savedsearches_id` IN('" . implode("', '", $ids)  . "')";
         return $DB->query($query);
      }
   }

   /**
    * Show user searches list
    *
    * @return void
    */
   function displayMine() {
      global $DB, $CFG_GLPI;

      $query = "SELECT `".$this->getTable()."`.*,
                       `glpi_savedsearches_users`.`id` AS IS_DEFAULT
                FROM `".$this->getTable()."`
                LEFT JOIN `glpi_savedsearches_users`
                  ON (`".$this->getTable()."`.`itemtype` = `glpi_savedsearches_users`.`itemtype`
                      AND `".$this->getTable()."`.`id` = `glpi_savedsearches_users`.`savedsearches_id`
                      AND `glpi_savedsearches_users`.`users_id` = '".Session::getLoginUserID()."')
                WHERE ";

      $privatequery = $query . "(`".$this->getTable()."`.`is_private`='1'
                     AND `".$this->getTable()."`.`users_id`='".Session::getLoginUserID()."') ";

      if ($this->canView()) {
         $publicquery = $query . "(`".$this->getTable()."`.`is_private`='0' ".
                        getEntitiesRestrictRequest("AND", $this->getTable(), "", "", true) . ")";
         $publicquery .= " ORDER BY `itemtype`, `name`";
      }

      $privatequery .= " ORDER BY `itemtype`, `name`";

      // get saved searches
      $searches = [
         'private'   => [],
         'public'    => []
      ];
      if ($result = $DB->query($privatequery)) {
         if ($numrows = $DB->numrows($result)) {
            while ($data = $DB->fetch_assoc($result)) {
               $searches['private'][$data['id']] = $data;
            }
         }
      }

      if ($this->canView()) {
         if ($result = $DB->query($publicquery)) {
            if ($numrows = $DB->numrows($result)) {
               while ($data = $DB->fetch_assoc($result)) {
                  $searches['public'][$data['id']] = $data;
               }
            }
         }
      }

      $ordered = array();

      // get personal order
      $user = new User();
      $personalorderfield = $this->getPersonalOrderField();

      if ($user->getFromDB(Session::getLoginUserID())) {
         $personalorder = importArrayFromDB($user->fields[$personalorderfield]);
      }
      if (!is_array($personalorder)) {
         $personalorder = array();
      }

      // Add on personal order
      if (count($personalorder)) {
         foreach ($personalorder as $val) {
            if (isset($searches['private'][$val])) {
               $ordered[$val] = $searches['private'][$val];
               unset($searches['private'][$val]);
            }
         }
      }

      // Add unsaved in order
      if (count($searches['private'])) {
         foreach ($searches['private'] as $key => $val) {
            $ordered[$key] = $val;
         }
      }

      // New: save order
      $store = array_keys($ordered);
      $user->update(array('id'                => Session::getLoginUserID(),
                           $personalorderfield => exportArrayToDB($store)));
      $searches['private'] = $ordered;

      $rand    = mt_rand();
      $numrows = $DB->numrows($result);

      echo "<div class='center' id='tabsbody' >";

      $colspan = 2;
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr><th colspan='$colspan' class='private_header'>" . sprintf(__('Private %1$s'), $this->getTypeName(count($searches['private']))) . "</th></tr>";
      echo $this->displaySavedSearchType($searches['private']);
      if ($this->canView()) {
         echo "<tr><th colspan='$colspan'>" . sprintf(__('Public %1$s'), $this->getTypeName(count($searches['public']))) . "</th></tr>";
         echo $this->displaySavedSearchType($searches['public']);
      }
      echo "</table></div>";
      Html::closeForm();

      if (count($searches['private']) || count($searches['public'])) {
         $js = "$(function() {
            $('.countSearches').on('click', function(e) {
               e.preventDefault();
               var _this = $(this);
               var _dest = _this.closest('tr').find('span.count');
               $.ajax({
                  url: _this.attr('href'),
                  beforeSend: function() {
                     var _img = '<span id=\'loading\'><img src=\'{$CFG_GLPI["root_doc"]}/pics/spinner.gif\' alt=\'" . __('Loading...') . "\'/></span>';
                     _dest.append(_img);
                  },
                  success: function(res) {
                     _dest.html(' (' + res.count + ')');
                  },
                  complete: function() {
                     $('#loading').remove();
                  }
               });
            });\n

            $('.slidepanel .default').on('click', function(e) {
               e.preventDefault();
               var _this = $(this);
               var _img = _this.find('img');
               var _currentsrc = _img.attr('src');
               $.ajax({
                  url: _this.attr('href').replace(/\/front\//, '/ajax/'),
                  beforeSend: function() {
                     _img.attr('src', '{$CFG_GLPI["root_doc"]}/pics/spinner.gif');
                  },
                  success: function(res) {
                     $('#showSavedSearches .contents').html(res);
                  },
                  error: function() {
                     alert('" . __('Default bookmark has not been changed!')  . "');
                     _img.attr('src', _currentsrc);
                  }
               });
            });\n

         });";

         echo Html::scriptBlock($js);
      }
   }


   private function displaySavedSearchType($searches) {
      global $CFG_GLPI;

      if ($totalcount = count($searches)) {
         $current_type      = -1;
         $number            = 0;
         $current_type_name = NOT_AVAILABLE;
         $search = new Search();
         $is_private = null;

         foreach ($searches as $key => $this->fields) {
            $number ++;
            if ($current_type != $this->fields['itemtype']) {
               $current_type      = $this->fields['itemtype'];
               $current_type_name = NOT_AVAILABLE;

               if ($current_type == "AllAssets") {
                  $current_type_name = __('Global');
               } else if ($item = getItemForItemtype($current_type)) {
                  $current_type_name = $item->getTypeName(Session::getPluralNumber());
               }
            }

            $count = null;
            //check if we want to count results
            if ($this->fields['do_count'] == self::COUNT_YES
               || $this->fields['do_count'] == self::COUNT_AUTO
               && $this->fields['last_execution_time'] <= $CFG_GLPI['max_time_for_count']) {
               //Do the same as self::getParameters() but getFromDB is useless
               $query_tab = array();
               parse_str($this->fields["query"], $query_tab);

               $params = null;
               if (class_exists($this->fields['itemtype']) || $this->fields['itemtype'] == 'AllAssets') {
                  $params = $this->prepareQueryToUse($this->fields["type"], $query_tab);
               }

               if (!$params) {
                  Toolbox::logDebug(
                     'Save search #' . $this->getID() . ' seems to be broken!'
                  );
               } else {
                  $data = $search->prepareDatasForSearch($current_type, $params);
                  $data['search']['sort'] = null;
                  $search->constructSQL($data);
                  $search->constructDatas($data);
                  $count = $data['data']['totalcount'];
               }
            } else {
               $info_message = ($this->fields['do_count'] == self::COUNT_NO) ?
                  __('Count for this saved search has been disabled.') :
                  __('Counting this saved search would take too long, it has been skipped.');
               if ($count === null) {
                  //no count, just inform the user
                  $count = "<img src='{$CFG_GLPI['root_doc']}/pics/info-small.png' alt='' title='$info_message'/>";
               }
            }

            if ($is_private === null) {
               $is_private = ($this->fields['is_private'] == 1);
            }

            echo "<tr class='tab_bg_1";
            if ($is_private) {
               echo " private' data-position='$number' data-id='{$this->getID()}";
            }
            echo "'>";
            echo "<td class='small no-wrap'>";
            if (is_null($this->fields['IS_DEFAULT'])) {
               echo "<a class='default' href=\"" . $this->getSearchURL() . "?action=edit&amp;".
                        "mark_default=1&amp;id=".$this->fields["id"]."\" alt=\"".
                        __s('Not default search')."\" title=\"".__s('Not default search')."\">".
                        "<img src=\"".$CFG_GLPI['root_doc']."/pics/bookmark_record.png\"></a>";
            } else {
               echo "<a class='default' href=\"" . $this->getSearchURL() . "?action=edit&amp;".
                        "mark_default=0&amp;id=".$this->fields["id"]."\" alt=\"".
                        __s('Default search')."\" title=\"".__s('Default search')."\">".
                        "<img src=\"".$CFG_GLPI['root_doc']."/pics/bookmark_default.png\"></a>";
            }

            echo "</td>";
            echo "<td>";
            $text = sprintf(
               __('%1$s on %2$s'),
               $this->fields['name'],
               $current_type_name
            );
            echo "<a class='savedsearchlink' href=\"" . $this->getSearchURL() . "?action=load&amp;id=".
                     $this->fields["id"]."\" title='".__('Click to load or drag and drop to reorder')."'>".
                     $text . "<span class='primary count'>$count</span></a>";
            echo "</td>";
            echo "</tr>";
         }

         if ($is_private) {
            //private saved searches can be ordered
            $js = "$(function() {
               $('.slidepanel .contents table').sortable({
                  items: 'tr.private',
                  placeholder: 'ui-state-highlight',
                  create: function(event, ui) {
                     $('tr.private td:first-child').each(function() {
                        $(this).prepend('<span class=\'drag\'><img src=\'{$CFG_GLPI['root_doc']}/pics/drag.png\' alt=\'\'/></span>');
                     });
                  },
                  stop: function (event, ui) {
                     var _ids = $('tr.private').map(function(idx, ele) {
                        return $(ele).data('id');
                     }).get();

                     $.ajax({
                        url: '{$CFG_GLPI["root_doc"]}/ajax/savedsearch.php?action=reorder',
                        data: {
                           ids: _ids
                        },
                        beforeSend: function() {
                           var _img = '<span id=\'loading\'><img src=\'{$CFG_GLPI["root_doc"]}/pics/spinner.gif\' alt=\'" . __('Loading...') . "\'/></span>';
                           $('.private_header').prepend(_img);
                        },
                        error: function() {
                           alert('" . __('Saved searches order cannot be saved!')  . "');
                        },
                        complete: function() {
                           $('#loading').remove();
                        }
                     });
                  }
               });
            });";

            echo Html::scriptBlock($js);
         }
      } else {
         echo "<tr class='tab_bg_1'><td colspan='3'>";
         echo sprintf(
            __('You have not recorded any %1$s yet'),
            mb_strtolower($this->getTypeName(1))
         );
         echo "</td></tr>";
      }
   }

   /**
    * Show saved searches list
    *
    * @param string  $target     Target to use for links
    * @param boolean $is_private Show private of public? (default 1)
    *
    * @return void
   **/
   function showSavedSearchesList($target, $is_private=1) {
      global $DB, $CFG_GLPI;

      if (!$is_private && !$this->canView()) {
         return false;
      }

      $query = "SELECT `".$this->getTable()."`.*,
                       `glpi_savedsearches_users`.`id` AS IS_DEFAULT
                FROM `".$this->getTable()."`
                LEFT JOIN `glpi_savedsearches_users`
                  ON (`".$this->getTable()."`.`itemtype` = `glpi_savedsearches_users`.`itemtype`
                      AND `".$this->getTable()."`.`id` = `glpi_savedsearches_users`.`savedsearches_id`
                      AND `glpi_savedsearches_users`.`users_id` = '".Session::getLoginUserID()."')
                WHERE ";

      if ($is_private) {
         $query .= "(`".$this->getTable()."`.`is_private`='1'
                     AND `".$this->getTable()."`.`users_id`='".Session::getLoginUserID()."') ";
      } else {
         $query .= "(`".$this->getTable()."`.`is_private`='0' ".
                     getEntitiesRestrictRequest("AND", $this->getTable(), "", "", true) . ")";
      }

      $query .= " ORDER BY `itemtype`, `name`";

      // get saved searches
      $searches = array();
      if ($result = $DB->query($query)) {
         if ($numrows = $DB->numrows($result)) {
            while ($data = $DB->fetch_assoc($result)) {
               $searches[$data['id']] = $data;
            }
         }
      }

      $ordered = array();

      // get personal order
      if ($is_private) {
         $user = new User();
         $personalorderfield = $this->getPersonalOrderField();

         if ($user->getFromDB(Session::getLoginUserID())) {
            $personalorder = importArrayFromDB($user->fields[$personalorderfield]);
         }
         if (!is_array($personalorder)) {
            $personalorder = array();
         }

         // Add on personal order
         if (count($personalorder)) {
            foreach ($personalorder as $val) {
               if (isset($searches[$val])) {
                  $ordered[$val] = $searches[$val];
                  unset($searches[$val]);
               }
            }
         }
      }
      // Add unsaved in order
      if (count($searches)) {
         foreach ($searches as $key => $val) {
            $ordered[$key] = $val;
         }
      }
      if ($is_private) {
         // New: save order
         $store = array_keys($ordered);
         $user->update(array('id'                => Session::getLoginUserID(),
                             $personalorderfield => exportArrayToDB($store)));
      }

      $rand    = mt_rand();
      $numrows = $DB->numrows($result);
      Html::openMassiveActionsForm('mass'.get_called_class().$rand);

      echo "<div class='center' id='tabsbody' >";
      $maactions = array('purge' => _x('button', 'Delete permanently'));
      if ($is_private) {
         $maactions[get_called_class().MassiveAction::CLASS_ACTION_SEPARATOR.'move_savedsearch'] = __('Move');
      }
      $massiveactionparams = array('num_displayed'     => $numrows,
                                    'container'        => 'mass'.get_called_class().$rand,
                                    'width'            => 600,
                                    'extraparams'      => array('is_private' => $is_private),
                                    'height'           => 200,
                                    'specific_actions' => $maactions);

      // No massive action on bottom
      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr>";
      echo "<th class='small'>".Html::getCheckAllAsCheckbox('mass'.get_called_class().$rand)."</th>";
      echo "<th class='center' colspan='2'>".$this->getTypeName(Session::getPluralNumber())."</th>";
      echo "<th class='small'>&nbsp;</th>";
      echo "<th class='small'>".__('Default view')."</th>";
      $colspan = 5;
      if ($is_private) {
         $colspan+=2;
         echo "<th class='small' colspan='2'>&nbsp;</th>";
      }
      echo "</tr>";

      if ($totalcount = count($ordered)) {
         $current_type      = -1;
         $number            = 0;
         $current_type_name = NOT_AVAILABLE;
         foreach ($ordered as $key => $this->fields) {
            $number ++;
            if ($current_type != $this->fields['itemtype']) {
               $current_type      = $this->fields['itemtype'];
               $current_type_name = NOT_AVAILABLE;

               if ($current_type == "AllAssets") {
                  $current_type_name = __('Global');
               } else if ($item = getItemForItemtype($current_type)) {
                  $current_type_name = $item->getTypeName(1);
               }
            }
            $canedit = $this->canEdit($this->fields["id"]);

            echo "<tr class='tab_bg_1'>";
            echo "<td width='10px'>";
            if ($canedit) {
               Html::showMassiveActionCheckBox(get_called_class(), $this->fields["id"]);
            } else {
               echo "&nbsp;";
            }
            echo "</td>";
            echo "<td>$current_type_name</td>";
            echo "<td>";
            if ($canedit) {
               echo "<a href=\"" . $this->getSearchURL() . "?action=edit&amp;id=".
                      $this->fields["id"]."\" title='"._sx('button', 'Update')."'>".
                      $this->fields["name"]."</a>";
            } else {
               echo $this->fields["name"];
            }
            echo "</td>";

            echo "<td style='white-space: nowrap;'><a href=\"" . $this->getSearchURL() . "?action=load&amp;id=".
                       $this->fields["id"]."\" class='vsubmit'>".__('Load')."</a>";
            echo "</td>";
            echo "<td class='center'>";
            if ($this->fields['type'] != self::URI) {
               if (is_null($this->fields['IS_DEFAULT'])) {
                  echo "<a href=\"" . $this->getSearchURL() . "?action=edit&amp;".
                         "mark_default=1&amp;id=".$this->fields["id"]."\" alt=\"".
                         __s('Not default search')."\" title=\"".__s('Not default search')."\">".
                         "<img src=\"".$CFG_GLPI['root_doc']."/pics/bookmark_record.png\" class='pointer'></a>";
               } else {
                  echo "<a href=\"" . $this->getSearchURL() . "?action=edit&amp;".
                         "mark_default=0&amp;id=".$this->fields["id"]."\" alt=\"".
                         __s('Default search')."\" title=\"".__s('Default search')."\">".
                         "<img src=\"".$CFG_GLPI['root_doc']."/pics/bookmark_default.png\" class='pointer'></a>";
               }
            }
            echo "</td>";
            if ($is_private) {
               if ($number != 1) {
                  echo "<td>";
                  Html::showSimpleForm($this->getSearchURL(), array('action' => 'up'), '',
                                       array('id'      => $this->fields["id"]),
                                       $CFG_GLPI["root_doc"]."/pics/puce-up.png");
                  echo "</td>";
               } else {
                  echo "<td>&nbsp;</td>";
               }

               if ($number != $totalcount) {
                  echo "<td>";
                  Html::showSimpleForm($this->getSearchURL(), array('action' => 'down'), '',
                                       array('id'      => $this->fields["id"]),
                                       $CFG_GLPI["root_doc"]."/pics/puce-down.png");
                  echo "</td>";
               } else {
                  echo "<td>&nbsp;</td>";
               }
            }
            echo "</tr>";
            $first = false;
         }
         echo "</table></div>";

         if ($is_private
             || Session::haveRight('bookmark_public', PURGE)) {
            $massiveactionparams['ontop']       = false;
            $massiveactionparams['forcecreate'] = true;
            Html::showMassiveActions($massiveactionparams);
         }
      } else {
         echo "<tr class='tab_bg_1'><td colspan='$colspan'>";
         echo sprintf(
            __('You have not recorded any %1$s yet'),
            $this->getTypeName(1)
         );
         echo "</td></tr></table></div>";
      }
      Html::closeForm();

   }


   /**
    * Save order
    *
    * @since version 9.2
    *
    * @param array $items Ordered ids
    *
    * @return boolean
    */
   function saveOrder(array $items) {
      global $DB;

      if (count($items)) {
         $user               = new User();
         $personalorderfield = $this->getPersonalOrderField();

         $user->update([
            'id'                 => Session::getLoginUserID(),
            $personalorderfield  => exportArrayToDB($items)
         ]);
         return true;
      }
      return false;
   }


   /**
    * Display buttons
    *
    * @param integer $type     SavedSearch type to use
    * @param integer $itemtype Device type of item where is the bookmark (default 0)
    *
    * @return void
   **/
   static function showSaveButton($type, $itemtype=0) {
      global $CFG_GLPI;

      echo " <a href='#' onClick=\"savesearch.dialog('open'); return false;\">";
      echo "<img src='".$CFG_GLPI["root_doc"]."/pics/bookmark_record.png'
             title=\"".__s('Save current search')."\" alt=\"".__s('Save current search')."\"
             class='calendrier'>";
      echo "</a>";

      Ajax::createModalWindow(
         'savesearch',
         $CFG_GLPI['root_doc'] . "/ajax/savedsearch.php?action=create&itemtype=$itemtype&type=$type&url=".
            rawurlencode($_SERVER["REQUEST_URI"]),
         [
            'title'       => __('Save current search')
         ]
      );
   }

   /**
    * Get personal order field name
    *
    * @return string
    */
   protected function getPersonalOrderField() {
      return 'privatebookmarkorder';
   }

   /**
    * Get all itemtypes used
    *
    * @return array of itemtypes
   **/
   static function getUsedItemtypes() {
      global $DB;

      $types= array();
      foreach ($DB->request("SELECT DISTINCT(`itemtype`)
                            FROM `" . static::getTable() . "`") as $data) {
         $types[] = $data['itemtype'];
      }
      return $types;
   }

   /**
    * Update bookmark execution time after it has been loaded
    *
    * @param integer $id   Saved search ID
    * @param integer $time Execution time, in milliseconds
    *
    * @return void
    */
   static public function updateExecutionTime($id, $time) {
      global $DB;

      $query = "UPDATE `". static::getTable() . "`
                SET `last_execution_time` = '$time'
                WHERE `id` = '$id'";
      $DB->query($query);
   }

   static function getSpecificValueToDisplay($field, $values, array $options=array()) {
      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'do_count':
            switch ($values[$field]) {
               case SavedSearch::COUNT_NO:
                  return __('No');
                  break;
               case SavedSearch::COUNT_YES:
                  return __('Yes');
                  break;
               case SavedSearch::COUNT_AUTO:
                  return ('Auto');
                  break;
            }
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {
      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;

      switch ($field) {
         case 'do_count' :
            $options['name']  = $name;
            $options['value'] = $values[$field];
            return self::dropdownDoCount($options);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }

   /**
    * Dropdown of do_count possible values
    *
    * @param array $options array of options
    *  - name     : select name (default is do_count)
    *  - value    : default value (default self::COUNT_AUTO)
    *  - display  : boolean if false get string
    *
    * @return void|string
   **/
   static function dropdownDoCount(array $options=array()) {

      $p['name']      = 'do_count';
      $p['value']     = self::COUNT_AUTO;
      $p['display']   = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $tab = [
         self::COUNT_AUTO  => _('Auto'),
         self::COUNT_YES   => __('Yes'),
         self::COUNT_NO    => __('No')
      ];

      return Dropdown::showFromArray($p['name'], $tab, $p);
   }

   /**
    * Set do_count from massive actions
    *
    * @param array   $ids      Items IDs
    * @param integer $do_count One of self::COUNT_*
    *
    * @return boolean
    */
   public function setDoCount(array $ids, $do_count) {
      global $DB;

      $query = "UPDATE " . $this->getTable() . " SET do_count=$do_count" .
         " WHERE id IN ('" . implode("', '", $ids) . "')";
      return $DB->query($query);
   }


   /**
    * Specific method to add where to a request
    *
    * @param string  $link       link string
    * @param boolean $nott       is it a negative search ?
    * @param string  $itemtype   item type
    * @param integer $ID         ID of the item to search
    * @param string  $searchtype searchtype used (equals or contains)
    * @param mixed   $val        item num in the request
    * @param integer $meta       is a meta search (meta=2 in search.class.php) (default 0)
    *
    * @return string where clause
    */
   public static function addWhere($link, $nott, $itemtype, $ID, $searchtype, $val, $meta = 0) {
      if ($ID == 11) { //search for defaults/not defaults
         if ($val == 0) {
            return 'glpi_savedsearches_users.users_id IS NULL';
         } else {
            return 'glpi_savedsearches_users.users_id IS NOT NULL';
         }
      }
   }
}
