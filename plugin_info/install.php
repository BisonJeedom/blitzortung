<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function blitzortung_install() {
    blitzortung::setupCron(1);
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function blitzortung_update() {
    blitzortung::setupCron(1);
    foreach (eqLogic::byType('blitzortung') as $eqLogic) {
        if ($eqLogic->getConfiguration("cfg_TemplateName", "") == '') {
            if ($eqLogic->getConfiguration('usePluginTemplate') == 1) { //Changement de méthode pour proposer le template : passage d'une checkbox à un select
                $eqLogic->setConfiguration("cfg_TemplateName", "horizontal");
                $eqLogic->setConfiguration("usePluginTemplate", "");
            } else {                
                $eqLogic->setConfiguration("cfg_TemplateName", "aucun");
            }
            $eqLogic->save();
            log::add('blitzortung', 'info', 'Mise à jour effectuée pour l\'équipement ' . $eqLogic->getHumanName());
        }
    }
}

// Fonction exécutée automatiquement après la suppression du plugin
function blitzortung_remove() {
    blitzortung::setupCron(0);
}
