<?php
/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\Chrome;

const USE_RELOAD_TIME_FOR_CAPACITOR = 1;
const USE_RELOAD_TIME_FOR_DPS = 2;
const USE_RELOAD_TIME_FOR_TANK = 4;

/**
 * Print the title of a loadout with additional state pictures.
 *
 * @param $name the name of the loadout (will be escaped).
 *
 * @param $viewpermission one of the VIEW_* contsants.
 *
 * @param $visibility one of the VISIBILITY_* constants.
 *
 * @param $author array containing the loadout author's info
 * (alliancename/id, corporationname/id).
 *
 * @param $relative relative path to the main page
 */
function print_loadout_title($name, $viewpermission, $visibility, $author, $relative = '.') {
	$pic = '';
	if($viewpermission == \Osmium\Fit\VIEW_PASSWORD_PROTECTED) {
		$pic = "<img src='$relative/static-".\Osmium\STATICVER."/icons/private.png' alt='(password-protected)' title='Password-protected fit' />";
	} else if($viewpermission == \Osmium\Fit\VIEW_ALLIANCE_ONLY) {
		$aname = ($author['apiverified'] === 't' && $author['allianceid'] > 0) ?
			htmlspecialchars($author['alliancename'], ENT_QUOTES) : 'My alliance';
		$pic = "<img src='$relative/static-".\Osmium\STATICVER."/icons/corporation.png' alt='($aname only)' title='$aname only' />";
	} else if($viewpermission == \Osmium\Fit\VIEW_CORPORATION_ONLY) {
		$cname = ($author['apiverified'] === 't') ?
			htmlspecialchars($author['corporationname'], ENT_QUOTES) : 'My corporation';
		$pic = "<img src='$relative/static-".\Osmium\STATICVER."/icons/corporation.png' alt='($cname only)' title='$cname only' />";
	} else if($viewpermission == \Osmium\Fit\VIEW_OWNER_ONLY) {
		$pic = "<img src='$relative/static-".\Osmium\STATICVER."/icons/onlyme.png' alt='(only visible by me)' title='Only visible by me' />";
	}

	if($visibility == \Osmium\Fit\VISIBILITY_PRIVATE) {
		$pic .= "<img src='$relative/static-".\Osmium\STATICVER."/icons/hidden.png' alt='(hidden)' title='This loadout will never appear on search results, and will never be indexed by search engines.' />";
	}
  
	echo "<span class='fitname'>".htmlspecialchars($name).$pic."</span>";
}

function print_formatted_attribute_category($identifier, $title, $titledata, $titleclass, $contents) {
	if($titleclass) $titleclass = " class='$titleclass'";
	echo "<section id='$identifier'>\n";
	echo "<h4$titleclass>$title <small>$titledata</small></h4>\n";
	echo "<div>\n$contents</div>\n";
	echo "</section>\n";
}

function print_formatted_engineering(&$fit, $relative, $capacitor) {
	ob_start();

	$slotsLeft = \Osmium\Dogma\get_ship_attribute($fit, 'turretSlotsLeft');
	$slotsTotal = \Osmium\Dogma\get_ship_attribute($fit, 'turretSlots');
	$formatted = \Osmium\Chrome\format_used($slotsTotal - $slotsLeft, $slotsTotal, 0, false, $overturrets);
	echo "<p class='overflow$overturrets'><img src='$relative/static-".\Osmium\STATICVER."/icons/turrethardpoints.png' alt='Turret hardpoints' title='Turret hardpoints' /><span id='turrethardpoints'>".$formatted."</span></p>\n";

	$slotsLeft = \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlotsLeft');
	$slotsTotal = \Osmium\Dogma\get_ship_attribute($fit, 'launcherSlots');
	$formatted = \Osmium\Chrome\format_used($slotsTotal - $slotsLeft, $slotsTotal, 0, false, $overlaunchers);
	echo "<p class='overflow$overlaunchers'><img src='$relative/static-".\Osmium\STATICVER."/icons/launcherhardpoints.png' alt='Launcher hardpoints' title='Launcher hardpoints' /><span id='launcherhardpoints'>".$formatted."</span></p>\n";

	list($captime, $capdelta) = \Osmium\Chrome\format_capacitor($capacitor);
	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/capacitor.png' alt='Capacitor' title='Capacitor' /><span id='capacitor'><span>".$captime."</span><br /><span>".$capdelta."</span></span></p>\n";

	$cpuUsed = \Osmium\Dogma\get_ship_attribute($fit, 'cpuLoad');
	$cpuTotal = \Osmium\Dogma\get_ship_attribute($fit, 'cpuOutput');
	$formatted = \Osmium\Chrome\format_used($cpuUsed, $cpuTotal, 2, true, $overcpu);
	echo "<p class='overflow$overcpu'><img src='$relative/static-".\Osmium\STATICVER."/icons/cpu.png' alt='CPU' title='CPU' /><span id='cpu'>".$formatted."</span></p>\n";

	$powerUsed = \Osmium\Dogma\get_ship_attribute($fit, 'powerLoad');
	$powerTotal = \Osmium\Dogma\get_ship_attribute($fit, 'powerOutput');
	$formatted = \Osmium\Chrome\format_used($powerUsed, $powerTotal, 2, true, $overpower);
	echo "<p class='overflow$overpower'><img src='$relative/static-".\Osmium\STATICVER."/icons/powergrid.png' alt='Powergrid' title='Powergrid' /><span id='power'>".$formatted."</span></p>\n";

	$upgradeCapacityUsed = \Osmium\Dogma\get_ship_attribute($fit, 'upgradeLoad');
	$upgradeCapacityTotal = \Osmium\Dogma\get_ship_attribute($fit, 'upgradeCapacity');
	$formatted = \Osmium\Chrome\format_used($upgradeCapacityUsed, $upgradeCapacityTotal, 2, true, $overupgrade);
	echo "<p class='overflow$overupgrade'><img src='$relative/static-".\Osmium\STATICVER."/icons/calibration.png' alt='Calibration' title='Calibration' /><span id='upgradecapacity'>".$formatted."</span></p>\n";

	print_formatted_attribute_category('engineering', 'Engineering', "<span title='Capacitor stability'>".$captime.' </span>', 'overflow'.max($overturrets, $overlaunchers, $overcpu, $overpower, $overupgrade), ob_get_clean());
}

function print_formatted_offense(&$fit, $relative, $reload = false) {
	ob_start();

	list($missiledps, $missilealpha) = \Osmium\Fit\get_damage_from_missiles($fit, $reload);
	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/missilelauncher.png' alt='Missile damage' title='Missile damage' /><span><span title='Missile volley (alpha)'>".format_number($missilealpha)."</span><br /><span title='Missile DPS'>".format_number($missiledps)."</span></span></p>\n";

	list($turretdps, $turretalpha) = \Osmium\Fit\get_damage_from_turrets($fit, $reload);
	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/turret.png' alt='Turret damage' title='Turret damage' /><span><span title='Turret volley (alpha)'>".format_number($turretalpha)."</span><br /><span title='Turret DPS'>".format_number($turretdps)."</span></span></p>\n";

	$dronedps = \Osmium\Fit\get_damage_from_drones($fit);
	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/drones.png' alt='Drone damage' title='Drone damage' /><span title='Drone DPS'>".format_number($dronedps)."</span></p>\n";	

	$dps = format_number($missiledps + $turretdps + $dronedps, -1);
	print_formatted_attribute_category('offense', 'Offense', "<span title='Total damage per second'>".$dps." dps</span>", '', ob_get_clean());
}

function print_formatted_defense(&$fit, $relative, $ehp, $cap, $dmgprofile, $reload = false) {
	ob_start();

	$resists = array();
	foreach($ehp as $k => $a) {
		if($k === 'ehp') continue;
		$resists[$k][] = "<td class='capacity'>".\Osmium\Chrome\format_number($a['capacity'])."</td>\n";
		$resists[$k][] = "<td class='emresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['em'])."</td>\n";
		$resists[$k][] = "<td class='thermalresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['thermal'])."</td>\n";
		$resists[$k][] = "<td class='kineticresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['kinetic'])."</td>\n";
		$resists[$k][] = "<td class='explosiveresist'>"
			.\Osmium\Chrome\format_resonance($a['resonance']['explosive'])."</td>\n";
	}

	$mehp = format_number($ehp['ehp']['min']);
	$aehp = format_number($ehp['ehp']['avg']);
	$Mehp = format_number($ehp['ehp']['max']);

	echo "<table id='resists'>\n<thead>\n<tr>\n";
	echo "<th><abbr title='Effective Hitpoints'>EHP</abbr></th>\n";
	echo "<th id='ehp'>\n";
	echo "<span title='EHP in the worst case (dealing damage with the lowest resistance)'>≥".$mehp."</span><br />\n";
	echo "<strong title='EHP in the average case (uniform damage repartition)'>".$aehp."</strong><br />\n";
	echo "<span title='EHP in the best case (dealing damage with the highest resistance)'>≤".$Mehp."</span></th>\n";
	echo "<td><img src='$relative/static-".\Osmium\STATICVER."/icons/r_em.png' alt='EM Resistance' title='EM Resistance' /></td>\n";
	echo "<td><img src='$relative/static-".\Osmium\STATICVER."/icons/r_thermal.png' alt='Thermal Resistance' title='Thermal Resistance' /></td>\n";
	echo "<td><img src='$relative/static-".\Osmium\STATICVER."/icons/r_kinetic.png' alt='Kinetic Resistance' title='Kinetic Resistance' /></td>\n";
	echo "<td><img src='$relative/static-".\Osmium\STATICVER."/icons/r_explosive.png' alt='Explosive Resistance' title='Explosive Resistance' /></td>\n";
	echo "</tr>\n</thead>\n<tfoot></tfoot>\n<tbody>\n<tr id='shield'>\n";
	echo "<th><img src='$relative/static-".\Osmium\STATICVER."/icons/shield.png' alt='Shield' title='Shield' /></th>\n";
	echo implode('', $resists['shield']);
	echo"</tr>\n<tr id='armor'>\n";
	echo "<th><img src='$relative/static-".\Osmium\STATICVER."/icons/armor.png' alt='Armor' title='Armor' /></th>\n";
	echo implode('', $resists['armor']);
	echo"</tr>\n<tr id='hull'>\n";
	echo "<th><img src='$relative/static-".\Osmium\STATICVER."/icons/hull.png' alt='Hull' title='Hull' /></th>\n";
	echo implode('', $resists['hull']);
	echo "</tr>\n</tbody>\n</table>\n";

	$layers = array(
		'hull' => array('hullrepair.png', 'Hull repairs',
		                       'Hull EHP repaired per second', true),
		'armor' => array('armorrepair.png', 'Armor repairs',
		                        'Armor EHP repaired per second', true),
		'shield' => array('shieldboost.png', 'Shield boost',
		                        'Shield EHP boost per second', true),
		'shield_passive' => array('shieldrecharge.png', 'Passive shield recharge',
		                          'Peak shield EHP recharged per second', false),
		);

	$rimg = "<img src='$relative/static-".\Osmium\STATICVER."/icons/tankreinforced.png' title='Reinforced tank' alt='Reinforced' />";
	$simg = "<img src='$relative/static-".\Osmium\STATICVER."/icons/tanksustained.png' title='Sustained tank' alt='Sustained' />";

	$rtotal = 0;
	$stotal = 0;
	foreach(\Osmium\Fit\get_tank($fit, $ehp, $cap, $dmgprofile, $reload) as $lname => $a) {
		list($reinforced, $sustained) = $a;
		if($reinforced == 0) continue;

		list($img, $alt, $title, $showboth) = $layers[$lname];

		echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/$img' alt='$alt' title='$title' />";
		if($showboth) {
			echo "<span>";
			echo $rimg."<span title='$title'>".format_number(1000 * $reinforced)."</span><br />";
			echo $simg."<span title='$title'>".format_number(1000 * $sustained)."</span>";
			echo "</span>";
		} else {
			echo "<span title='$title'>".format_number(1000 * $reinforced)."</span>";
		}
		echo "</p>\n";

		$rtotal += $reinforced;
		$stotal += $sustained;
	}

	$tehp = format_number($ehp['ehp']['avg'], -2);
	$subtitles[] = '<span title="Average EHP">'.$tehp.' ehp</span>';

	if($rtotal > 0) {
		$rtotal = format_number(1000 * $rtotal, -1);
		$subtitles[] = '<span title="Combined reinforced tank">'.$rtotal.' dps</span>';
	}
	if($stotal > 0) {
		$stotal = format_number(1000 * $stotal, -1);
		if($stotal !== $rtotal) {
			$subtitles[] = '<span title="Combined sustained tank">'.$stotal.' dps</span>';
		}
	}
	
	print_formatted_attribute_category('defense', 'Defense', implode(' – ', $subtitles), '', ob_get_clean());
}

function print_formatted_navigation(&$fit, $relative) {
	ob_start();

	$maxvelocity = round(\Osmium\Dogma\get_ship_attribute($fit, 'maxVelocity'));
	$agility = \Osmium\Dogma\get_ship_attribute($fit, 'agility');
	$aligntime = -log(0.25) * \Osmium\Dogma\get_ship_attribute($fit, 'mass') * $agility / 1000000;
	$warpspeed = \Osmium\Dogma\get_ship_attribute($fit, 'warpSpeedMultiplier') * 3.0;
	$warpstrength = -\Osmium\Dogma\get_ship_attribute($fit, 'warpScrambleStatus');
	$fpoints = 'point'.(abs($warpstrength) == 1 ? '' : 's');

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/propulsion.png' alt='Propulsion' title='Propulsion' /><span title='Maximum velocity'>".format_number($maxvelocity)." m/s</span></p>\n";

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/agility.png' alt='Agility' title='Agility' /><span><span title='Agility modifier'>".format_number($agility, 3)."x</span><br /><span title='Time to align'>".format_number($aligntime)." s</span></span></p>\n";

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/warpcore.png' alt='Warp' title='Warp' /><span><span title='Warp speed'>".round($warpspeed, 1)." AU/s</span><br /><span title='Warp core strength'>".$warpstrength." ".$fpoints."</span></span></p>\n";

	print_formatted_attribute_category('navigation', 'Navigation', '<span title="Maximum velocity">'.format_number($maxvelocity, -1).' m/s</span>', '', ob_get_clean());
}

function print_formatted_targeting(&$fit, $relative) {
	ob_start();

	$scanstrength = array();
	$maxtype = null;
	foreach(array('radar', 'ladar', 'magnetometric', 'gravimetric') as $t) {
		$scanstrength[$t] = \Osmium\Dogma\get_ship_attribute($fit, 'scan'.ucfirst($t).'Strength');
		if($maxtype === null || $scanstrength[$t] > $scanstrength[$maxtype]) {
			$maxtype = $t;
		}
	}

	$targetrange = \Osmium\Dogma\get_ship_attribute($fit, 'maxTargetRange');
	$numtargets = (int)min(\Osmium\Dogma\get_char_attribute($fit, 'maxLockedTargets'),
	                  \Osmium\Dogma\get_ship_attribute($fit, 'maxLockedTargets'));
	$ftargets = 'target'.($numtargets !== 1 ? 's' : '');
	$scanres = \Osmium\Dogma\get_ship_attribute($fit, 'scanResolution');
	$sigradius = \Osmium\Dogma\get_ship_attribute($fit, 'signatureRadius');

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/targeting_".$maxtype.".png' alt='".ucfirst($maxtype)." strength' title='".ucfirst($maxtype)." strength' /><span><span title='Maximum locked targets'>".$numtargets." ".$ftargets."</span><br /><span title='Sensor strength'>".round($scanstrength[$maxtype], 1)." points</span></span></p>\n";

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/targeting.png' alt='Targeting range' title='Targeting range' /><span><span title='Targeting range'>".format_range($targetrange)."</span><br /><span title='Scan resolution'>".format_number($scanres)." mm</span></span></p>\n";

	echo "<p><img src='$relative/static-".\Osmium\STATICVER."/icons/radius.png' alt='Signature radius' title='Signature radius' /><span>".format_range($sigradius)."</span></p>\n";

	print_formatted_attribute_category(
		'targeting', 'Targeting',
		$numtargets.' '.$ftargets.' @ '.format_range($targetrange, true),
		'', ob_get_clean()
	);
}

function print_formatted_misc(&$fit) {
	ob_start();

	echo "<table>\n<tbody>\n";

	$missing = array();
	$prices = \Osmium\Fit\get_estimated_price($fit, $missing);
	$grand_total = 0;
	$fprices = array();

	foreach($prices as $section => $p) {
		if($p !== 'N/A') {
			$grand_total += $p;
			$p = format_isk($p);
		}

		$fprices[] = $section.': '.$p;
	}

	if($grand_total === 0 && count($missing) > 0) {
		$grand_total = 'N/A';
	} else {
		$grand_total = format_isk($grand_total);
		if(count($missing) > 0) {
			$grand_total = '≥ '.$grand_total;
		}
	}

	$fprices = implode(',<br />', $fprices);
	if($fprices === '') $fprices = '<small>N/A</small>';

	if(count($missing) > 0) {
		$missing = implode(",&#10;", array_unique(array_map('Osmium\Fit\get_typename', $missing)));
		$fprices = "<span title='Estimate of the following items unavailable:&#10;"
			.htmlspecialchars($missing, ENT_QUOTES)."'>".$fprices.'</span>';
	}

	echo "<tr><th>Estimated price:</th><td>{$fprices}</td></tr>\n";

	$yield = \Osmium\Fit\get_mining_yield($fit);
	if($yield > 0) {
		$yield *= 3600000; /* From m³/ms to m³/h */
		$yield = number_format($yield);
		echo "<tr><th>Mining yield:</th><td>$yield m<sup>3</sup>/h</td></tr>\n";
	}

	$cargo = \Osmium\Dogma\get_ship_attribute($fit, 'capacity');
	echo "<tr><th>Cargo capacity:</th><td>".round($cargo, 2)." m<sup>3</sup></td></tr>\n";

	if(count($fit['drones']) > 0) {
		$dcrange = \Osmium\Dogma\get_char_attribute($fit, 'droneControlDistance');
		echo "<tr><th>Drone control range:</th><td>".format_range($dcrange)."</td></tr>\n";
	}

	echo "</tbody>\n</table>\n";
	print_formatted_attribute_category('misc', 'Miscellaneous', $grand_total, '', ob_get_clean());
}

function print_formatted_loadout_attributes(&$fit, $relative = '.', $opts = null) {
	if($opts === null) {
		/* NB: if you change the defaults here, also change the
		 * default exported values in CLF export function. */
		$opts = USE_RELOAD_TIME_FOR_CAPACITOR;
	}

	$dmgprofile = array('em' => 1, 'thermal' => 1, 'explosive' => 1, 'kinetic' => 1);

	$cap = \Osmium\Fit\get_capacitor_stability($fit, $opts & USE_RELOAD_TIME_FOR_CAPACITOR);
	$ehp = \Osmium\Fit\get_ehp_and_resists($fit, $dmgprofile);

	print_formatted_engineering($fit, $relative, $cap);
	print_formatted_offense($fit, $relative, $opts & USE_RELOAD_TIME_FOR_DPS);
	print_formatted_defense($fit, $relative, $ehp, $cap, $dmgprofile, $opts & USE_RELOAD_TIME_FOR_TANK);
	print_formatted_navigation($fit, $relative);
	print_formatted_targeting($fit, $relative);
	print_formatted_misc($fit);
}

function get_formatted_loadout_attributes(&$fit, $relative = '.', $opts = null) {
	ob_start();
	print_formatted_loadout_attributes($fit, $relative, $opts);
	return ob_get_clean();
}

function print_loadout_common_footer(&$fit, $relative, $clftoken) {
	\Osmium\Chrome\print_js_code(
		"osmium_cdatastaticver = ".\Osmium\CLIENT_DATA_STATICVER.";
osmium_staticver = ".\Osmium\STATICVER.";
osmium_relative = '".$relative."';
osmium_token = '".\Osmium\State\get_token()."';
osmium_clftoken = '".$clftoken."';
osmium_clf = ".json_encode(\Osmium\Fit\export_to_common_loadout_format_1($fit, true, true, true)).";
osmium_skillsets = ".json_encode(\Osmium\Fit\get_available_skillset_names_for_account()).";"
	);

	echo "<script type='application/javascript' src='".$relative."/static-1/rawdeflate.min.js'></script>";

	\Osmium\Chrome\print_js_snippet('tabs');
	\Osmium\Chrome\print_js_snippet('modal');
	\Osmium\Chrome\print_js_snippet('context_menu');
	\Osmium\Chrome\print_js_snippet('loadout_common');
	\Osmium\Chrome\print_js_snippet('show_info');
	\Osmium\Chrome\print_js_snippet('formatted_attributes');
}
