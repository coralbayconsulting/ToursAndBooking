/**
 * Tour Date → Limited vehicles: live Avail. = Max − Sold (manual Sold field).
 * “Add limited vehicles from tour” prepends rows from parent tour pricing + Limited by default.
 */
(function ($) {
	'use strict';

	function lvCfg() {
		return typeof bstLimitedVehicles !== 'undefined' ? bstLimitedVehicles : {};
	}

	function lvI18n(key, fallback) {
		var c = lvCfg();
		if (c.i18n && c.i18n[key]) {
			return c.i18n[key];
		}
		return fallback || '';
	}

	function getTourPostIdFromAcf() {
		var $tour = $('.acf-field[data-name="tour"]');
		var $sel = $tour.find('select').first();
		if ($sel.length) {
			var v = $sel.val();
			if (v) {
				return parseInt(v, 10) || 0;
			}
		}
		var $hid = $tour.find('input[type="hidden"]').filter(function () {
			var n = $(this).attr('name') || '';
			return n.indexOf('[tour]') !== -1 || n.indexOf('field_67896a73458d0') !== -1;
		}).first();
		if ($hid.length) {
			var hv = $hid.val();
			if (hv) {
				return parseInt(hv, 10) || 0;
			}
		}
		return 0;
	}

	function getExistingLimitedVehicleIds($wrap) {
		var ids = {};
		$wrap.find('tbody .acf-row').not('.acf-clone').each(function () {
			var $s = $(this).find('[data-name="limited_vehicle"] select').first();
			if ($s.length && $s.val()) {
				ids[String($s.val())] = true;
			}
		});
		return ids;
	}

	function ensureVehicleOption($select, idStr, title) {
		var has = false;
		$select.find('option').each(function () {
			if ($(this).val() === idStr) {
				has = true;
				return false;
			}
		});
		if (!has) {
			$select.append(new Option(title || '#' + idStr, idStr, true, true));
		}
	}

	function setLimitedRowVehicleAndMax($row, item) {
		var idStr = String(item.vehicle_id);
		var title = item.title || '#' + idStr;

		var $wrapField = $row.find('.acf-field[data-key="field_696e8b1a0a002"]');
		var $veh = $row.find('[data-name="limited_vehicle"] select').first();
		if (!$veh.length && $wrapField.length) {
			$veh = $wrapField.find('select').first();
		}

		if (typeof acf !== 'undefined' && acf.getField && $wrapField.length) {
			try {
				var acfFld = acf.getField($wrapField);
				if (acfFld && typeof acfFld.val === 'function') {
					var $acfIn =
						typeof acfFld.$input === 'function' ? acfFld.$input() : $veh;
					if ($acfIn && $acfIn.length) {
						ensureVehicleOption($acfIn, idStr, title);
					}
					acfFld.val(item.vehicle_id);
					if (typeof acfFld.render === 'function') {
						acfFld.render();
					}
				}
			} catch (err) {}
		}

		if ($veh.length) {
			ensureVehicleOption($veh, idStr, title);
			$veh.val(idStr);
			$veh.trigger('change');
			if ($veh.data('select2')) {
				$veh.trigger('change.select2');
			}
		}

		$row.find('input[type="hidden"][name*="field_696e8b1a0a002"]').val(idStr);

		var $max = $row.find('[data-name="limited_vehicle_max"] input[type="number"], [data-name="limited_vehicle_max"] input').first();
		if ($max.length) {
			$max.val(item.max);
			$max.trigger('input');
		}
		updateAvailDisplay($row);
	}

	function fillLimitedVehicleRow($row, item) {
		var attempts = 0;
		function tick() {
			attempts += 1;
			var $sel = $row.find('[data-name="limited_vehicle"] select').first();
			if (!$sel.length) {
				$sel = $row.find('.acf-field[data-key="field_696e8b1a0a002"] select').first();
			}
			if ($sel.length) {
				setLimitedRowVehicleAndMax($row, item);
				setTimeout(function () {
					setLimitedRowVehicleAndMax($row, item);
				}, 80);
				return;
			}
			if (attempts < 30) {
				setTimeout(tick, 70);
				return;
			}
			setLimitedRowVehicleAndMax($row, item);
		}
		tick();
	}

	function addLimitedRowsFromItems(items, onDone) {
		if (!items.length || typeof acf === 'undefined') {
			if (typeof onDone === 'function') {
				onDone();
			}
			return;
		}
		var repeater = acf.getField('field_696e8b1a0a001');
		if (!repeater || typeof repeater.add !== 'function') {
			if (typeof onDone === 'function') {
				onDone();
			}
			return;
		}
		var i = 0;
		function next() {
			if (i >= items.length) {
				var $wrap = $('.acf-field[data-name="limited_vehicles"]');
				bindRepeater($wrap);
				if (typeof onDone === 'function') {
					onDone();
				}
				return;
			}
			var item = items[i];
			i += 1;
			repeater.add();
			setTimeout(function () {
				var $wrap = $('.acf-field[data-name="limited_vehicles"]');
				var $row = $wrap.find('tbody .acf-row').not('.acf-clone').last();
				fillLimitedVehicleRow($row, item);
				setTimeout(next, 220);
			}, 200);
		}
		next();
	}

	function setupAddFromTourButton() {
		var $actions = $('.acf-field[data-name="limited_vehicles"] .acf-actions');
		if (!$actions.length || $actions.data('bstLvFromTour')) {
			return;
		}
		$actions.data('bstLvFromTour', 1);
		if (!$actions.prev('.bst-lv-add-from-tour-note').length) {
			var noteText = lvI18n(
				'addFromTourNote',
				'Before using this button, mark each relevant vehicle as Limited by default on its Vehicle edit screen (and ensure it is linked on the parent tour’s Vehicle Pricing). Only those vehicles are added.'
			);
			$actions.before($('<p class="description bst-lv-add-from-tour-note"></p>').text(noteText));
		}
		$actions.addClass('bst-lv-repeater-actions');
		var label = lvI18n('addFromTour', 'Add limited vehicles from tour');
		$actions.prepend('<span class="bst-lv-repeater-actions-spacer"></span>');
		$actions.prepend(
			'<button type="button" class="button bst-lv-add-from-tour">' + $('<div/>').text(label).html() + '</button>'
		);
	}

	function onAddFromTourClick(e) {
		e.preventDefault();
		var tourId = getTourPostIdFromAcf();
		if (!tourId) {
			window.alert(lvI18n('selectTour', 'Select a parent Tour first.'));
			return;
		}
		var c = lvCfg();
		if (!c.ajaxUrl || !c.nonce) {
			return;
		}
		var $wrap = $('.acf-field[data-name="limited_vehicles"]');
		var existing = getExistingLimitedVehicleIds($wrap);
		$.ajax({
			url: c.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'bst_lv_limited_from_tour',
				nonce: c.nonce,
				tour_id: tourId,
			},
		})
			.done(function (res) {
				if (!res || !res.success) {
					var msg =
						res && res.data && res.data.message
							? res.data.message
							: lvI18n('requestError', 'Could not load tour vehicles. Try again.');
					window.alert(msg);
					return;
				}
				var raw = (res.data && res.data.items) || [];
				if (!raw.length) {
					window.alert(lvI18n('noMatches', 'No vehicles on this tour are marked Limited by default.'));
					return;
				}
				var toAdd = [];
				for (var j = 0; j < raw.length; j++) {
					var it = raw[j];
					if (!it || !it.vehicle_id) {
						continue;
					}
					if (existing[String(it.vehicle_id)]) {
						continue;
					}
					toAdd.push({
						vehicle_id: parseInt(it.vehicle_id, 10),
						max: parseInt(it.max, 10) > 0 ? parseInt(it.max, 10) : 1,
						title: typeof it.title === 'string' ? it.title : '',
					});
					existing[String(it.vehicle_id)] = true;
				}
				if (!toAdd.length) {
					window.alert(lvI18n('allPresent', 'Those vehicles are already listed in Limited vehicles.'));
					return;
				}
				var tmpl = lvI18n('added', 'Added %d limited vehicle row(s).');
				var n = toAdd.length;
				addLimitedRowsFromItems(toAdd, function () {
					window.alert(tmpl.replace('%d', String(n)));
				});
			})
			.fail(function () {
				window.alert(lvI18n('requestError', 'Could not load tour vehicles. Try again.'));
			});
	}

	function findMaxSoldInputs($row) {
		var $max = $row.find('[data-name="limited_vehicle_max"] input[type="number"], [data-name="limited_vehicle_max"] input').first();
		var $sold = $row.find('[data-name="limited_vehicle_sold"] input[type="number"], [data-name="limited_vehicle_sold"] input').first();
		return { $max: $max, $sold: $sold };
	}

	function findAvailTarget($row) {
		var $wrap = $row.find('[data-name="limited_vehicle_avail_display"]');
		var $msg = $wrap.find('.acf-message');
		if ($msg.length) {
			return $msg;
		}
		return $wrap.find('.acf-input');
	}

	function updateAvailDisplay($row) {
		var inputs = findMaxSoldInputs($row);
		var maxVal = parseInt(inputs.$max.val(), 10);
		if (isNaN(maxVal)) {
			maxVal = 0;
		}
		var soldVal = parseInt(inputs.$sold.val(), 10);
		if (isNaN(soldVal)) {
			soldVal = 0;
		}
		var $avail = findAvailTarget($row);
		if (!$avail.length) {
			return;
		}
		if (maxVal <= 0) {
			$avail.html('—');
			return;
		}
		var avail = Math.max(0, maxVal - soldVal);
		$avail.html('<span class="bst-lv-avail-num">' + avail + '</span>');
	}

	function applyVehicleLocks($wrap) {
		$wrap.find('.acf-field[data-name="limited_vehicle"]').each(function () {
			var $f = $(this);
			var $sel = $f.find('select');
			if ($f.hasClass('bst-lv-vehicle-locked')) {
				if ($sel.length) {
					$sel.prop('disabled', true);
					if ($sel.hasClass('select2-hidden-accessible') && $sel.data('select2')) {
						$sel.trigger('change.select2');
					}
				}
			} else if ($sel.length) {
				$sel.prop('disabled', false);
			}
		});
	}

	function bindRepeater($wrap) {
		if (!$wrap.length) {
			return;
		}
		applyVehicleLocks($wrap);
		$wrap.off('.bstlv');
		$wrap.on('input.bstlv change.bstlv', '.acf-row [data-name="limited_vehicle_max"] input, .acf-row [data-name="limited_vehicle_sold"] input', function () {
			var $inp = $(this);
			var $row = $inp.closest('.acf-row');
			if ($inp.closest('[data-name="limited_vehicle_max"]').length) {
				clearTimeout($inp.data('bstLvTimer'));
				$inp.data(
					'bstLvTimer',
					setTimeout(function () {
						updateAvailDisplay($row);
					}, 150)
				);
			} else {
				updateAvailDisplay($row);
			}
		});
		$wrap.find('.acf-row').each(function () {
			updateAvailDisplay($(this));
		});
	}

	function refreshAllRows() {
		var $wrap = $('.acf-field[data-name="limited_vehicles"]');
		$wrap.find('.acf-row').each(function () {
			updateAvailDisplay($(this));
		});
	}

	function initLimitedVehicles() {
		var $wrap = $('.acf-field[data-name="limited_vehicles"]');
		setupAddFromTourButton();
		bindRepeater($wrap);
		setTimeout(refreshAllRows, 100);
		setTimeout(refreshAllRows, 400);
	}

	$(document).on('click', '.bst-lv-add-from-tour', onAddFromTourClick);

	if (typeof acf !== 'undefined') {
		acf.addAction('ready', initLimitedVehicles);
		acf.addAction('append', function ($el) {
			if ($el.hasClass('acf-row') && $el.closest('.acf-field[data-name="limited_vehicles"]').length) {
				var $wrap = $el.closest('.acf-field[data-name="limited_vehicles"]');
				applyVehicleLocks($wrap);
				setTimeout(function () {
					updateAvailDisplay($el);
				}, 50);
			}
		});
	}

	$(function () {
		if (typeof acf === 'undefined') {
			initLimitedVehicles();
		}
		setTimeout(setupAddFromTourButton, 300);
	});
})(jQuery);
