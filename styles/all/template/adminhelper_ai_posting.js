(function() {
	'use strict';

	var panel = document.getElementById('adminhelper-ai-upload-panel');
	var fileList = document.getElementById('file-list');
	var checkedInput = document.getElementById('geoexplo_ai_generated_attachment_ids');
	var forcedInput = document.getElementById('geoexplo_ai_forced_attachment_ids');
	var stateInput = document.getElementById('adminhelper_ai_attachment_state_b64');

	if (!panel || !fileList || !checkedInput || !forcedInput) {
		return;
	}

	var labels = {
		label: String(panel.getAttribute('data-label') || ''),
		locked: String(panel.getAttribute('data-locked-label') || ''),
		via: String(panel.getAttribute('data-via-label') || ''),
		sourceC2pa: String(panel.getAttribute('data-source-c2pa') || ''),
		sourceMetadata: String(panel.getAttribute('data-source-metadata') || ''),
		sourcePrompt: String(panel.getAttribute('data-source-prompt') || ''),
		providerGemini: String(panel.getAttribute('data-provider-gemini') || ''),
		providerChatgpt: String(panel.getAttribute('data-provider-chatgpt') || ''),
		providerGrok: String(panel.getAttribute('data-provider-grok') || ''),
		providerDallE: String(panel.getAttribute('data-provider-dall_e') || ''),
		providerMidjourney: String(panel.getAttribute('data-provider-midjourney') || ''),
		providerStableDiffusion: String(panel.getAttribute('data-provider-stable_diffusion') || ''),
		providerComfyui: String(panel.getAttribute('data-provider-comfyui') || ''),
		providerAdobeFirefly: String(panel.getAttribute('data-provider-adobe_firefly') || ''),
		providerImagen: String(panel.getAttribute('data-provider-imagen') || ''),
		providerBlackForestLabs: String(panel.getAttribute('data-provider-black_forest_labs') || ''),
		providerFlux: String(panel.getAttribute('data-provider-flux') || ''),
		providerLeonardo: String(panel.getAttribute('data-provider-leonardo') || ''),
		providerIdeogram: String(panel.getAttribute('data-provider-ideogram') || ''),
		providerInvokeai: String(panel.getAttribute('data-provider-invokeai') || ''),
		providerAutomatic1111: String(panel.getAttribute('data-provider-automatic1111') || ''),
		providerNovelai: String(panel.getAttribute('data-provider-novelai') || ''),
		providerPlaygroundai: String(panel.getAttribute('data-provider-playgroundai') || ''),
		providerOpenai: String(panel.getAttribute('data-provider-openai') || '')
	};

	var stateByAttachId = {};
	var checkedIds = {};
	var forcedIds = {};

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"]/g, function(ch) {
			switch (ch) {
				case '&': return '&amp;';
				case '<': return '&lt;';
				case '>': return '&gt;';
				case '"': return '&quot;';
				default: return ch;
			}
		});
	}

	function parseCsv(value) {
		var out = {};
		String(value || '').split(',').forEach(function(raw) {
			var id = parseInt(String(raw || '').trim(), 10);
			if (id > 0) {
				out[id] = true;
			}
		});
		return out;
	}

	function parseStateMap() {
		stateByAttachId = {};
		if (!stateInput || !stateInput.value) {
			return;
		}
		try {
			var decoded = JSON.parse(atob(String(stateInput.value)));
			if (!decoded || typeof decoded !== 'object') {
				return;
			}
			Object.keys(decoded).forEach(function(key) {
				var attachId = parseInt(key, 10);
				if (!(attachId > 0) || !decoded[key] || typeof decoded[key] !== 'object') {
					return;
				}
				stateByAttachId[attachId] = decoded[key];
			});
		} catch (e) {}
	}

	function loadHiddenState() {
		checkedIds = parseCsv(checkedInput.value);
		forcedIds = parseCsv(forcedInput.value);
		parseStateMap();
	}

	function rowAttachmentId(row) {
		if (!row) {
			return 0;
		}
		var id = parseInt(String(row.getAttribute('data-attach-id') || '0').trim(), 10);
		if (id > 0) {
			return id;
		}
		var hiddenAttachId = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[attach_id]"]');
		if (hiddenAttachId) {
			id = parseInt(String(hiddenAttachId.value || '0').trim(), 10);
			if (id > 0) {
				row.setAttribute('data-attach-id', String(id));
				return id;
			}
		}
		return 0;
	}

	function rowFileName(row) {
		var link = row.querySelector('.file-name a');
		if (link && link.textContent) {
			return String(link.textContent || '').trim();
		}
		var nameNode = row.querySelector('.file-name');
		return nameNode ? String(nameNode.textContent || '').trim() : '';
	}

	function isImageRow(row) {
		var mimetypeInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[mimetype]"]');
		if (mimetypeInput && /^image\//i.test(String(mimetypeInput.value || ''))) {
			return true;
		}
		return /\.(jpe?g|png|gif|webp|bmp|avif|heic|heif|tiff?)$/i.test(rowFileName(row));
	}

	function getSourceLabel(source) {
		switch (String(source || '')) {
			case 'c2pa':
				return labels.sourceC2pa;
			case 'metadata':
				return labels.sourceMetadata;
			case 'prompt':
				return labels.sourcePrompt;
			default:
				return '';
		}
	}

	function getProviderLabel(provider) {
		switch (String(provider || '')) {
			case 'gemini':
				return labels.providerGemini;
			case 'chatgpt':
				return labels.providerChatgpt;
			case 'grok':
				return labels.providerGrok;
			case 'dall_e':
				return labels.providerDallE;
			case 'midjourney':
				return labels.providerMidjourney;
			case 'stable_diffusion':
				return labels.providerStableDiffusion;
			case 'comfyui':
				return labels.providerComfyui;
			case 'adobe_firefly':
				return labels.providerAdobeFirefly;
			case 'imagen':
				return labels.providerImagen;
			case 'black_forest_labs':
				return labels.providerBlackForestLabs;
			case 'flux':
				return labels.providerFlux;
			case 'leonardo':
				return labels.providerLeonardo;
			case 'ideogram':
				return labels.providerIdeogram;
			case 'invokeai':
				return labels.providerInvokeai;
			case 'automatic1111':
				return labels.providerAutomatic1111;
			case 'novelai':
				return labels.providerNovelai;
			case 'playgroundai':
				return labels.providerPlaygroundai;
			case 'openai':
				return labels.providerOpenai;
			default:
				return '';
		}
	}

	function mergeStateFromHiddenData(row, state, attachId) {
		var generatedInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_generated]"]');
		var forcedInputEl = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_forced]"]');
		var providerInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_provider]"]');
		var sourceInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_source]"]');
		var reasonInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_reason]"]');
		var statusInput = row.querySelector('input[type="hidden"][name^="attachment_data["][name$="[adminhelper_ai_scan_status]"]');

		if (generatedInput) {
			state.generated = parseInt(generatedInput.value || '0', 10) === 1 ? 1 : 0;
		}
		if (forcedInputEl) {
			state.forced = parseInt(forcedInputEl.value || '0', 10) === 1 ? 1 : 0;
		}
		if (providerInput) {
			state.provider = String(providerInput.value || '');
		}
		if (sourceInput) {
			state.source = String(sourceInput.value || '');
		}
		if (reasonInput) {
			state.reason = String(reasonInput.value || '');
		}
		if (statusInput) {
			state.scan_status = String(statusInput.value || '');
		}
		if (attachId > 0) {
			stateByAttachId[attachId] = state;
		}
		return state;
	}

	function resolveRowState(row) {
		var attachId = rowAttachmentId(row);
		var state = attachId > 0 && stateByAttachId[attachId]
			? Object.assign({}, stateByAttachId[attachId])
			: {};

		state.generated = parseInt(state.generated || 0, 10) === 1 ? 1 : 0;
		state.forced = parseInt(state.forced || 0, 10) === 1 ? 1 : 0;
		state.provider = String(state.provider || '');
		state.source = String(state.source || '');
		state.reason = String(state.reason || '');
		state.scan_status = String(state.scan_status || '');

		state = mergeStateFromHiddenData(row, state, attachId);

		if (attachId > 0 && forcedIds[attachId]) {
			state.generated = 1;
			state.forced = 1;
		}
		if (attachId > 0 && checkedIds[attachId]) {
			state.generated = 1;
		}

		return state;
	}

	function writeCsvIfChanged(input, map) {
		var value = Object.keys(map).map(function(key) {
			return parseInt(key, 10);
		}).filter(function(id) {
			return id > 0;
		}).sort(function(a, b) {
			return a - b;
		}).join(',');

		if (String(input.value || '') !== value) {
			input.value = value;
		}
	}

	function syncStateFromDom() {
		var nextChecked = {};
		var nextForced = {};
		var rows = fileList.querySelectorAll('tr.attach-row');

		Array.prototype.forEach.call(rows, function(row) {
			var control = row.querySelector('.adminhelper-ai-attach-checkbox');
			if (!control) {
				return;
			}
			var attachId = rowAttachmentId(row);
			if (!(attachId > 0)) {
				return;
			}

			if (control.checked) {
				nextChecked[attachId] = true;
			}
			if (control.disabled) {
				nextForced[attachId] = true;
				nextChecked[attachId] = true;
			}
		});

		checkedIds = nextChecked;
		forcedIds = nextForced;
		writeCsvIfChanged(checkedInput, checkedIds);
		writeCsvIfChanged(forcedInput, forcedIds);
	}

	function ensureControl(row) {
		if (!row || row.classList.contains('attach-row-tpl') || row.id === 'attach-row-tpl' || !isImageRow(row)) {
			return;
		}

		var commentCell = row.querySelector('.attach-comment');
		if (!commentCell) {
			return;
		}

		var container = row.querySelector('.geoexplo-attach-tools');
		if (!container) {
			container = row.querySelector('.adminhelper-ai-attach-tools');
		}
		if (!container) {
			container = document.createElement('div');
			container.className = 'adminhelper-ai-attach-tools';
			commentCell.appendChild(container);
		}

		var wrap = row.querySelector('.adminhelper-ai-attach-control');
		if (!wrap) {
			wrap = document.createElement('div');
			wrap.className = 'adminhelper-ai-attach-control';
			wrap.innerHTML =
				'<label>' +
					'<input type="checkbox" class="adminhelper-ai-attach-checkbox" />' +
					'<span>' + escapeHtml(labels.label) + '</span>' +
				'</label>' +
				'<span class="adminhelper-ai-attach-status"></span>';
			container.appendChild(wrap);
		}

		var checkbox = wrap.querySelector('.adminhelper-ai-attach-checkbox');
		var status = wrap.querySelector('.adminhelper-ai-attach-status');
		var state = resolveRowState(row);

		checkbox.checked = !!state.generated;
		checkbox.disabled = !!state.forced;

		if (state.forced) {
			var providerLabel = getProviderLabel(state.provider);
			var sourceLabel = getSourceLabel(state.source);
			var detail = providerLabel;
			if (sourceLabel) {
				detail = detail
					? (detail + ' ' + labels.via + ' ' + sourceLabel)
					: sourceLabel;
			}
			status.textContent = detail ? (labels.locked + ' ' + detail) : labels.locked;
		} else {
			status.textContent = '';
		}

		if (wrap.getAttribute('data-adminhelper-ai-bound') !== '1') {
			wrap.setAttribute('data-adminhelper-ai-bound', '1');
			checkbox.addEventListener('change', syncStateFromDom);
		}
	}

	function decorateRows() {
		loadHiddenState();
		var rows = fileList.querySelectorAll('tr.attach-row');
		Array.prototype.forEach.call(rows, ensureControl);
		syncStateFromDom();
	}

	var decorateScheduled = false;
	function scheduleDecorate() {
		if (decorateScheduled) {
			return;
		}
		decorateScheduled = true;
		var runner = function() {
			decorateScheduled = false;
			decorateRows();
		};
		if (window.requestAnimationFrame) {
			window.requestAnimationFrame(runner);
		} else {
			window.setTimeout(runner, 0);
		}
	}

	decorateRows();

	if (window.MutationObserver) {
		var observer = new MutationObserver(function() {
			scheduleDecorate();
		});
		observer.observe(fileList, {
			childList: true,
			subtree: true
		});
	}

	window.setTimeout(scheduleDecorate, 400);
	window.setTimeout(scheduleDecorate, 1500);
})();
