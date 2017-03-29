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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class nagioschecks extends eqLogic {
    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'nagios_plugins';
        $file = dirname(__FILE__) . '/../../resources/check_apt';
        if (is_executable($file)) {
            $return['state'] = 'ok';
        } else {
            $return['state'] = 'nok';
        }
        return $return;
    }

    public static function dependancy_install() {
        $cmd = 'sudo chmod +x ' . dirname(__FILE__) . '/../../resources/*';
        exec($cmd);
    }

    public static function cronDaily() {
        foreach (eqLogic::byType('nagioschecks', true) as $nagioschecks) {
            foreach ($nagioschecks->getCmd() as $cmd) {
                $cmd->setConfiguration('alert', 0);
                $cmd->setConfiguration('alertsend', 0);
                $cmd->setConfiguration('cmdexec', 0);
                $cmd->save();
            }
        }
    }

    public static function cron5() {
        foreach (eqLogic::byType('nagioschecks', true) as $nagioschecks) {
            $nagioschecks->getInformations('5');
        }
    }

    public static function cron15() {
        foreach (eqLogic::byType('nagioschecks', true) as $nagioschecks) {
            $nagioschecks->getInformations('15');
        }
    }

    public static function cron30() {
        foreach (eqLogic::byType('nagioschecks', true) as $nagioschecks) {
            $nagioschecks->getInformations('30');
        }
    }

    public static function cronHourly() {
        foreach (eqLogic::byType('nagioschecks', true) as $nagioschecks) {
            $nagioschecks->getInformations('60');
        }
    }

    public function postAjax() {
        foreach ($this->getCmd() as $cmd) {
            $cmd->setTemplate("mobile",'line' );
            $cmd->setTemplate("dashboard",'line' );
            $cmd->setSubType("binary");
            $cmd->save();
        }
        $this->getInformations('all');
    }

    public function alertCmd($titre, $message) {
        if ($this->getConfiguration('alert','') != '') {
            $cmd = cmd::byId(str_replace('#','',$this->getConfiguration('alert')));
            $options['title'] = 'Alerte sur ' . $titre;
            $options['message'] = $titre . " avec statut " . $message;
            $cmd->execCmd($options);
        }
    }

    public function getInformations($cron) {

        foreach ($this->getCmd() as $cmd) {
            $tempo = $cmd->getConfiguration('cron');
            if ($tempo == '') {
                $tempo = '15';
            }
            if ($cmd->getConfiguration('cron') == $cron || 'all' == $cron) {
                $alert = $cmd->getConfiguration('alert');
                if ($alert == '') {
                    $alert = 0;
                }
                $notifalert = $cmd->getConfiguration('notifalert','');

                $cline = $cmd->getConfiguration('check') . ' ' . $cmd->getConfiguration('options');
                if ($cmd->getConfiguration('ssh') == '1') {
                    $cline = $this->getConfiguration('sshpath') . $cline;
                }  else if (strrpos($cline,'/') !== false) {
                    $cline = dirname(__FILE__) . '/../../resources' . $cline;
                } else {
                    $cline = '/usr/lib/nagios/plugins/' . $cline;
                }
                $cline = ($cmd->getConfiguration('sudo') == '1') ? 'sudo ' . $cline : $cline;

                if ($cmd->getConfiguration('ssh') == '1') {
                    $cline = '/usr/lib/nagios/plugins/check_by_ssh -H ' . $this->getConfiguration('sshhost') . ' -l ' . $this->getConfiguration('sshuser') . ' -p ' . $this->getConfiguration('sshport') . ' -i ' . $this->getConfiguration('sshkey') . ' -C "' . $cline . '"';
                }
                log::add('nagioschecks', 'debug', 'Command : ' . $cline);
                unset($output);
                $output = array();
                exec($cline, $output, $return_var);
                //$return_var = '0';
                log::add('nagioschecks', 'debug', 'Result : ' . $return_var . ' label ' . $output[0] . ' notif ' . $notifalert . ' ' . $alert);
                $value = ($return_var == 0) ? 1 : 0;
                if ($value == 0 && $notifalert != '') {
                    if ($alert >= $notifalert) {
                        $this->alertCmd($cmd->getName(), $output[0]);
                        $cmd->setConfiguration('alertsend', 1);
                    } else {
                        $newalerte = $alert + 1;
                        $cmd->setConfiguration('alert', $newalerte);
                        $cmd->setConfiguration('alertsend', 0);
                    }
                    if ($cmd->getConfiguration('cmdexec') != 1 && $cmd->getConfiguration('cmdalert','') != '') {
                        $cmdexec = cmd::byId(str_replace('#','',$cmd->getConfiguration('cmdalert')));
                        $cmdexec->execCmd();
                        $cmd->setConfiguration('cmdexec', 1);
                    }
                } else {
                    $cmd->setConfiguration('alert', 0);
                    $cmd->setConfiguration('alertsend', 0);
                    $cmd->setConfiguration('cmdexec', 0);
                }
                $cmd->setConfiguration('value', $value);
                $cmd->setConfiguration('code', $return_var);
                $cmd->setConfiguration('status', $output[0]);
                $cmd->save();
                $cmd->event($value);


                //Traitement métriques
                if (strpos($output[0], '|') !== false) {
                    $metric = substr($output[0], 0, strpos($output[0], '|'));
                    $cmd->setConfiguration('hasMetric', '1');
                    $cmd->save();
                    //log::add('nagioschecks', 'debug', $metric);
                }

            }
        }
        return ;
    }

}

class nagioschecksCmd extends cmd {
    public function execute($_options = null) {
        if ($_options['option'] == 'status') {
            return $this->getConfiguration('status');
        } else if ($_options['option'] == 'code') {
            return $this->getConfiguration('code');
        } else {
            return $this->getConfiguration('value');
        }
    }

}

?>
