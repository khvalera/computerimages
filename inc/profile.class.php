<?php

// Don't allow direct access
if (!defined('GLPI_ROOT')) {
    die(__('Direct access is not allowed', 'computerimages') . "\n");
}

class PluginComputerimagesProfile extends Profile {

   static $rightname = "config";

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getID() > 0
              && $item->fields['interface'] == 'central') {
         return self::createTabEntry(__('Computer images', 'computerimages'));
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $pfProfile = new self();
      $pfProfile->showForm($item->getID());
      return true;
   }

    /**
    * Show profile form
    *
    * @param $items_id integer id of the profile
    * @param $target value url of target
    *
    * @return nothing
    **/
   function showForm($ID, array $options = []) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [READ, CREATE, DELETE]))) {
         $profile = new Profile();
         echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($ID);

      $rights = $this->getAllRights();
      $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                    'default_class' => 'tab_bg_2',
                                                    'title'         => __('Computer images', 'computerimages')
                                                   ]);
      if ($canedit) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";
   }

   static function uninstallProfile() {
      $pfProfile = new self();
      $a_rights = $pfProfile->getAllRights();
      foreach ($a_rights as $data) {
         ProfileRight::deleteProfileRights([$data['field']]);
      }
   }

   // Defining access rights that will be displayed in the Profile
   function getAllRights() {
      $a_rights = [
          ['rights' => [
            READ    => __('View', 'computerimages'),
            CREATE  => __('Upload', 'computerimages'),
            DELETE  => __('Delete', 'computerimages') ],
                'label'     => __('Access rights', 'computerimages'),
                'field'     => 'plugin_computerimages_profile'
          ],
      ];
      return $a_rights;
   }

   /**
    * @param $profiles_id  integer
    */
   static function createFirstAccess($profiles_id) {
      include_once(Plugin::getPhpDir('computerimages')."/inc/profile.class.php");
      $profile = new self();
      foreach ($profile->getAllRights() as $right) {
         self::addDefaultProfileInfos($profiles_id,
                                      [$right['field'] => ALLSTANDARDRIGHT]);
      }
   }

   static function removeRights() {
      $profile = new self();
      foreach ($profile->getAllRights() as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
         ProfileRight::deleteProfileRights([$right['field']]);
      }
   }

   /**
   * Init profiles during installation :
   * - add rights in profile table for the current user's profile
   * - current profile has all rights on the plugin
   */
   static function initProfile() {
      $pfProfile = new self();
      $profile   = new Profile();
      $a_rights  = $pfProfile->getAllRights();

      foreach ($a_rights as $data) {
         if (!countElementsInTable("glpi_profilerights", ['name' => $data['field']])) {
            ProfileRight::addProfileRights([$data['field']]);
            $_SESSION['glpiactiveprofile'][$data['field']] = 0;
         }
      }

      // Add all rights to current profile of the user
      if (isset($_SESSION['glpiactiveprofile'])) {
         $dataprofile       = [];
         $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
         $profile->getFromDB($_SESSION['glpiactiveprofile']['id']);
         foreach ($a_rights as $info) {
            if (is_array($info)
                && ((!empty($info['itemtype'])) || (!empty($info['rights'])))
                  && (!empty($info['label'])) && (!empty($info['field']))) {

               if (isset($info['rights'])) {
                  $rights = $info['rights'];
               } else {
                  $rights = $profile->getRightsFor($info['itemtype']);
               }
               foreach (array_keys($rights) as $right) {
                  $dataprofile['_'.$info['field']][$right] = 1;
                  $_SESSION['glpiactiveprofile'][$data['field']] = $right;
               }
            }
         }
         $profile->update($dataprofile);
      }
   }
}
