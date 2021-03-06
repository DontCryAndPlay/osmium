/* Osmium
 * Copyright (C) 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
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

/**
 * Try to auto-guess "best" initial values. Returns an array of three
 * values [ tsrOptimal, tvOptimal, tdOptimal ].
 */
osmium_probe_optimals_from_ia = function(ia) {
	var tsrc = 0, tsrs = 0, tvc = 0, tvs = 0, tdc = 0, tds = 0;

	for(var i = 0; i < ia.length; ++i) {
		var a = ia[i].raw;

		if(!("damagetype" in a)) continue;

		if("sigradius" in a) {
			++tsrc;
			tsrs += a.sigradius;
		}

		if("expradius" in a) {
			++tsrc;
			tsrs += a.expradius;
		}

		if(a.damagetype === "turret" && "range" in a) {
			++tdc;
			tds += a.range / 1000.0;
		}
	}

	return [
		tsrc === 0 ? 250 : Math.round(tsrs / tsrc),
		tvc === 0 ? 0 : Math.round(tvs / tvc),
		tdc === 0 ? 1 : Math.round(tds / tdc),
	];
};

/**
 * Try to auto-guess graph boundaries with some given
 * constraints. Parameters tsr, tv, td can be filled or left
 * out. Returns an array of three values [ tsrMax, tvMax, tdMax ].
 */
osmium_probe_boundaries_from_ia = function(ia, tsr, tv, td) {
	var tsrmax = 50, tvmax = 50, tdmax = 5000;
	var a;

	if(isNaN(td)) {
		for(var j = 0; j < ia.length; ++j) {
			a = ia[j].raw;
			if(!("damagetype" in a)) continue;

			var m = Math.min(
				("range" in a && "falloff" in a) ? (a.range + 3 * a.falloff) : Infinity,
				("maxrange" in a) ? (a.maxrange * 1.1) : Infinity,
				("controlrange" in a) ? (a.controlrange * 1.1) : Infinity
			);

			if(isFinite(m)) tdmax = Math.max(tdmax, m);
		}

		tdmax /= 1000;
	} else {
		tdmax = td;
	}

	if(isNaN(tsr)) {
		for(var j = 0; j < ia.length; ++j) {
			a = ia[j].raw;
			if(!("damagetype" in a)) continue;

			if("sigradius" in a) {
				tsrmax = Math.max(tsrmax, a.sigradius * 3);
				continue;
			}

			if("expradius" in a) {
				tsrmax = Math.max(tsrmax, a.expradius * 3);
				continue;
			}
		}
	} else {
		tsrmax = tsr;
	}

	if(isNaN(tv)) {
		for(var j = 0; j < ia.length; ++j) {
			a = ia[j].raw;
			if(!("damagetype" in a)) continue;

			if("trackingspeed" in a && "range" in a && "falloff" in a) {
				if(a.damagetype === "combatdrone" && a.maxvelocity > 0) {
					continue;
				}

				tvmax = Math.max(
					tvmax,
					Math.min(a.damagetype === "combatdrone" ? 2500 : 12000,
							 (a.range + a.falloff) * a.trackingspeed)
				);
				continue;
			}

			if("expvelocity" in a) {
				tvmax = Math.max(tvmax, a.expvelocity * 8);
				continue;
			}
		}
	} else {
		tvmax = tv;
	}

	return [ tsrmax, tvmax, tdmax ];
};

/**
 * Return a color from a parameter between 0 and 1. The color is red
 * for 1, and will smoothly go through all the color spectrum to reach
 * transparent-ish purple at zero.
 */
osmium_heat_color = function(t) {
	return "hsla("
		+ Math.round((1 - t) * 360).toString()
		+ ", 100%, 50%, "
		+ Math.min(1, t).toFixed(2)
		+ ")";
};

/**
 * Generate labels and append them next to a canvas element.
 *
 * @param ctx the root element to add the labels to
 * @param canvas the canvas element
 * @param xlabel text to label the X axis with
 * @param ylabel text to label the Y axis with
 */
osmium_graph_gen_labels = function(ctx, canvas, xlabel, ylabel) {
	var xl, yl;
	ctx.append(xl = $(document.createElement('span')).addClass('xlabel').text(xlabel));
	ctx.append(yl = $(document.createElement('span')).addClass('ylabel').text(ylabel));

	var cpos = canvas.offset();

	xl.offset({
		top: cpos.top + canvas.height() + 4,
		left: cpos.left + canvas.width() / 2 - xl.width() / 2
	});

	/* Rotating first gives different results on Chromium/Firefox */
	yl.offset({
		top: cpos.top + canvas.height() / 2 - yl.height() / 2,
		left: cpos.left - yl.width() / 2 - yl.height() / 2 - 4
	}).addClass('rotated');
};

/**
 * Draw a labeled grid using a given canvas context.
 *
 * @param cctx the canvas context to draw with
 * @param cw canvas width
 * @param ch canvas height
 * @param xmin minimum value for X axis
 * @param xmax maximum value for X axis
 * @param xsteps minimum number of vertical guides to draw
 * @param ymin minimum value for Y axis
 * @param ymax maximum value for Y axis
 * @param ysteps minimum value of horizontal guides to draw
 * @param axisopacity opacity (between 0 and 1) of the drawn guides
 * @param labelopacity opacity (between 0 and 1) of the drawn labels
 */
osmium_graph_draw_grid = function(cctx, cw, ch, xmin, xmax, xsteps, ymin, ymax, ysteps, axisopacity, labelopacity) {
	var steps = [ 50000, 20000, 10000,
				  5000, 2000, 1000,
				  500, 200, 100,
				  50, 20, 10,
				  5, 2, 1,
				  .5, .2, .1,
				  .05, .02, .01,
				  .005, .002, .001,
				  .0005, .0002, .0001 ];

	var xstep = 1, ystep = 1;
	for(var i = 0; i < steps.length; ++i) {
		if((xmax - xmin) / steps[i] >= xsteps) {
			xstep = steps[i];
			break;
		}
	}
	for(var i = 0; i < steps.length; ++i) {
		if((ymax - ymin) / steps[i] >= ysteps) {
			ystep = steps[i];
			break;
		}
	}

	cctx.beginPath();
	cctx.font = '0.8em "Droid Sans"';
	cctx.fillStyle = "hsla(0, 0%, 50%, " + labelopacity.toString() + ")";

	cctx.textAlign = "center";
	cctx.textBaseline = "bottom";
	for(var x = Math.ceil(xmin / xstep) * xstep; x < xmax; x += xstep) {
		if(x === xmin) continue;

		var xc = Math.floor(cw * (x - xmin) / (xmax - xmin)) + 0.5;
		cctx.moveTo(xc, 0.5);
		cctx.lineTo(xc, ch - 0.5);
		cctx.fillText(x.toString(), xc, ch - 0.5);
	}

	cctx.textAlign = "left";
	cctx.textBaseline = "middle";
	for(var y = Math.ceil(ymin / ystep) * ystep; y < ymax; y += ystep) {
		if(y === ymin) continue;

		var yc = Math.floor(ch * (y - ymin) / (ymax - ymin)) + 0.5;
		cctx.moveTo(0.5, ch - yc);
		cctx.lineTo(cw - 0.5, ch - yc);
		cctx.fillText(y.toString(), 2.5, ch - yc);
	}

	cctx.strokeStyle = "hsla(0, 0%, 50%, " + axisopacity.toString() + ")";
	cctx.stroke();
};

/**
 * Get the average DPS of a turret-like weapon.
 *
 * http://wiki.eveuniversity.org/Turret_Damage
 *
 * @param dps the raw DPS of the turret
 * @param trackingspeed tracking speed of the turret, in rad/s
 * @param sigresolution signature resolution of the turret, in meters
 * @param range optimal range of the turret, in meters
 * @param falloff falloff range of the turret, in meters
 * @param tsr target signature radius, in meters
 * @param tv target velocity, in m/s
 * @param td target distance, in km
 */
osmium_turret_damage_f = function(dps, trackingspeed, sigresolution, range, falloff, tsr, tv, td) {
	if(tv == 0 && td == 0) td = .001;
	if(tsr == 0) return 0;

	var cth = Math.pow(
		0.5,
		Math.pow(
			((tv / (1000 * td)) / trackingspeed) * (sigresolution / tsr),
			2
		) + Math.pow(
			Math.max(0, (1000 * td) - range) / falloff,
			2
		)
	);

	return (
		Math.min(cth, 0.01) * 3 + Math.max(cth - 0.01, 0) * (0.49 + (cth + 0.01) / 2)
	) * dps;
};

/**
 * Get the average DPS of a missile-like weapon.
 *
 * http://wiki.eveuniversity.org/Missile_Damage
 *
 * @param dps the raw DPS of the missile launcher
 * @param maxrange the maximum range of the missile
 * @param expradius explosion radius of the missile
 * @param expvelocity explosion velocity of the missile
 * @param drf damage reduction factor
 * @param drs damage reduction sensitivity
 * @param tsr target signature radius, in meters
 * @param tv target velocity, in m/s
 * @param td target distance, in km
 */
osmium_missile_damage_f = function(dps, maxrange, expradius, expvelocity, drf, drs, tsr, tv, td) {
	if(1000 * td > maxrange || dps == 0) return 0;

	return dps * Math.min(
		1,
		tsr / expradius,
		(tsr != 0 && expvelocity != 0) ?
			Math.pow((tsr / expradius) * (expvelocity / tv), Math.log(drf) / Math.log(drs))
			: 0
	);
};

/** Get the average DPS of a fitted type. */
osmium_get_dps_from_type_internal = function(a, tsr, tv, td) {
	if(!("damagetype" in a)) return 0;

	if(a.damagetype === "combatdrone" || a.damagetype === "fighter" || a.damagetype === "fighterbomber") {
		if(a.damagetype === "combatdrone" && "controlrange" in a && 1000 * td > a.controlrange) return 0;

		if(a.maxvelocity == 0) {
			/* Sentry drone */
			return osmium_turret_damage_f(
				a.damage / a.duration,
				a.trackingspeed, a.sigradius, a.range, a.falloff,
				tsr, tv, td
			);
		}

		/* XXX: this is a very simplistic model, totally inaccurate
		 * guesswork. Critique & improvements most welcomed! */

		/* Drone tries to keep orbit at flyrange m @ cruisespeed m/s */
		/* After a full cycle, assume the drone will use MWD to
		 * reenter orbit distance */
		var ddur = a.duration;

		if(tv > a.cruisespeed) {
			if(tv >= a.maxvelocity) {
				/* Drone will never catch up */
				ddur = Infinity;
			} else {
				ddur += (tv - a.cruisespeed) * a.duration / (a.maxvelocity - tv);
			}
		}

		if(a.damagetype === "fighterbomber") {
			return osmium_missile_damage_f(
				a.damage / ddur,
				a.maxrange, a.expradius, a.expvelocity, a.drf, a.drs,
				tsr, tv, a.flyrange / 1000.0
			);
		}

		return osmium_turret_damage_f(
			a.damage / ddur,
			a.trackingspeed, a.sigradius, a.range, a.falloff,
			tsr, a.cruisespeed, a.flyrange / 1000.0
		);
	}

	if(a.damagetype === "turret") {
		return osmium_turret_damage_f(
			a.damage / a.duration,
			a.trackingspeed, a.sigradius, a.range, a.falloff,
			tsr, tv, td
		);
	}

	if(a.damagetype === "missile") {
		return osmium_missile_damage_f(
			a.damage / a.duration,
			a.maxrange, a.expradius, a.expvelocity, a.drf, a.drs,
			tsr, tv, td
		);
	}

	if(a.damagetype === "smartbomb") {
		if(1000 * td > a.maxrange) return 0;
		return a.damage / a.duration;
	}

	return 0;
};

/** @internal */
osmium_get_dps_internal = function(ia, args) {
	var dps = 0;
	for(var j = 0; j < ia.length; ++j) {
		dps += osmium_get_dps_from_type_internal(ia[j].raw, args[0], args[1], args[2]);
	}
	return 1000 * dps;
};

/**
 * Draw a line graph for every set of attributes in ia_map, using colors in color_map.
 *
 * @param ctx the root element to append the canvas and labels into
 * @param xlabel the X label
 * @param xmin the minimum X value
 * @param xmax the maximum X value
 * @param genfunc_x a function which takes the X coordinate and returns an array [ tsr, tv, td ]
 * @param dpsmin the minimum Y value
 * @param dpsmax the maximum Y value, leave null to autodetect
 */
osmium_draw_dps_graph_1d = function(ia_map, color_map, ctx,
									xlabel, xmin, xmax, genfunc_x, dpsmin, dpsmax) {
	ctx.empty();

	var canvas = document.createElement('canvas');
	var cctx = canvas.getContext('2d');
	var cw, ch;
	canvas = $(canvas);
	ctx.append($(document.createElement('div')).addClass('cctx').append(canvas));
	canvas.attr('width', cw = canvas.width());
	canvas.attr('height', ch = canvas.height());

	osmium_graph_gen_labels(ctx, canvas, xlabel, "Damage per second");

	var x, dps, px, py;

	if(!dpsmax) {
		dpsmax = 10;

		for(var i = 0; i <= cw; ++i) {
			x = xmin + (i / cw) * (xmax - xmin);

			for(var k in ia_map) {
				if(!("ia" in ia_map[k])) continue;
				dpsmax = Math.max(dpsmax, osmium_get_dps_internal(ia_map[k].ia, genfunc_x(x)));
			}
		}

		dpsmax *= 1.05;
	}

	osmium_graph_draw_grid(cctx, cw, ch, xmin, xmax, 8, dpsmin, dpsmax, 4, 0.15, 0.5);

	for(var k in ia_map) {
		if(!("ia" in ia_map[k])) continue;
		cctx.beginPath();
		cctx.moveTo(0, 0);

		for(var i = 0; i <= cw; ++i) {
			x = xmin + (i / cw) * (xmax - xmin);
			dps = osmium_get_dps_internal(ia_map[k].ia, genfunc_x(x));
			px = i + 0.5;
			py = Math.floor(ch * (1 - (dps - dpsmin) / (dpsmax - dpsmin))) + 0.5;

			if(i === 0) {
				cctx.moveTo(px, py);
			} else {
				cctx.lineTo(px, py);
			}
		}

		cctx.strokeStyle = color_map[k];
		cctx.lineWidth = 3;
		cctx.stroke();
	}
};

/**
 * Draw a 2d graph. Most parameters are similar to the 1d version.
 *
 * @param genfunc_xy a function that takes two parameters (the X,Y
 * coordinates) and returns an array [ tsr, tv, td ].
 *
 * @param cololfunc a function that takes one parameter, a map of
 * array [ DPS, MaxDPS ] values and returns a color.
 *
 * @param pixelsize the size of the rectangles drawn on the
 * graph. Higher values means less rectangles to draw, but results in
 * a blockier graph.
 *
 * @returns global maximum dps
 */
osmium_draw_dps_graph_2d = function(ia_map, colorfunc, ctx,
									xlabel, xmin, xmax, ylabel, ymin, ymax,
									genfunc_xy, pixelsize) {
	ctx.empty();

	var canvas = document.createElement('canvas');
	var cctx = canvas.getContext('2d');
	var cw, ch;
	canvas = $(canvas);
	ctx.append($(document.createElement('div')).addClass('cctx').append(canvas));
	canvas.attr('width', cw = canvas.width());
	canvas.attr('height', ch = canvas.height());

	osmium_graph_gen_labels(ctx, canvas, xlabel, ylabel);

	cctx.moveTo(0, ch);

	var x, y, px, py, localmax = {}, globalmax = 10, hps = pixelsize / 2;

	for(var k in ia_map) {
		if(!("ia" in ia_map[k])) continue;
		var ia = ia_map[k].ia;
		localmax[k] = 0;
	
		for(var i = 0; i <= cw; i += pixelsize) {
			x = xmin + ((i + hps) / cw) * (xmax - xmin);

			for(var j = 0; j <= ch; j += pixelsize) {
				y = ymin + ((j + hps) / ch) * (ymax - ymin);
				localmax[k] = Math.max(localmax[k], osmium_get_dps_internal(ia, genfunc_xy(x, y)));
			}
		}

		globalmax = Math.max(localmax[k], globalmax);
	}

	var dps = {};

	for(var k in ia_map) {
		dps[k] = [ 0, Math.max(1, localmax[k]) ];
	}

	for(var i = 0; i <= cw; i += pixelsize) {
		x = xmin + ((i + hps) / cw) * (xmax - xmin);

		for(var j = 0; j <= ch; j += pixelsize) {
			y = ymin + ((j + hps) / ch) * (ymax - ymin);

			for(var k in ia_map) {
				if(!("ia" in ia_map[k])) continue;

				dps[k][0] = osmium_get_dps_internal(ia_map[k].ia, genfunc_xy(x, y));
			}

			cctx.fillStyle = colorfunc(dps);
			cctx.fillRect(i, ch - j, pixelsize, pixelsize);
		}
	}

	osmium_graph_draw_grid(cctx, cw, ch, xmin, xmax, 8, ymin, ymax, 4, 0.15, 0.75);

	return globalmax;
};

/** Draw a legend for colored 2d graphs. */
osmium_draw_dps_legend = function(ctx, maxdps, heatfunc) {
	ctx.find('div.cctx').addClass('twodim');

	var lcanvas = document.createElement('canvas');
	var lctx = lcanvas.getContext('2d');
	var lw, lh;
	lcanvas = $(lcanvas);
	ctx.append($(document.createElement('div')).addClass('legend').append(lcanvas));
	lcanvas.attr('width', lw = lcanvas.width());
	lcanvas.attr('height', lh = lcanvas.height());

	for(var i = 0; i <= lh; ++i) {
		lctx.fillStyle = heatfunc(i / lh);
		lctx.fillRect(0, lh - i, 100, 1);
	}

	var dlabel = $(document.createElement('span')).text('DPS');
	ctx.append(dlabel);
	var lpos = lcanvas.parent().offset();
	dlabel.offset({
		top: lpos.top + lcanvas.parent().height() + 5,
		left: lpos.left + lcanvas.parent().width() / 2 - dlabel.width() / 2
	});

	lpos = lcanvas.offset();
	var nlabels = 6;
	for(var i = 0; i <= nlabels; ++i) {
		dlabel = $(document.createElement('span')).addClass('dpslabel')
			.text(Math.round((i / nlabels) * maxdps).toString());
		ctx.append(dlabel);
		dlabel.offset({
			top: Math.min(
				Math.max(
					lpos.top + lcanvas.height() * (1 - i / nlabels) - dlabel.height() / 2,
					lpos.top
				),
				lpos.top + lcanvas.height() - dlabel.height()
			),
			left: lpos.left - dlabel.width() - 4
		});
	}
};
