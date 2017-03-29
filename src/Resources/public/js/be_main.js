;(function($, window) {

var renameElement = function(element) {

	element = $(element);

	var index = element.getAllPrevious('.rsce_list_item').length;
	var oldName = element.get('data-rsce-name');

	var newName = oldName.split('__');
	newName[newName.length - 1] = index;
	newName = newName.join('__');

	var attributes = [
		'name',
		'id',
		'href',
		'onclick',
		'for',
		'value',
		'data-rsce-name'
	];

	element.set('data-rsce-name', newName);
	element.getChildren('[data-rsce-label]').each(function(el) {
		el.set(
			'text',
			el.get('data-rsce-label').split('%s').join(index + 1)
		);
	});

	element.getElements(
		'[' + attributes.join('*="' + oldName + '"],[') + '*="' + oldName + '"]'
	).each(function(el) {
		attributes.each(function(attribute) {
			if (el.get(attribute)) {
				el.set(attribute, el.get(attribute).split(oldName).join(newName));
			}
		});
	});

	element.getElements('script').each(function(el) {
		if (el.text && el.text.indexOf(oldName) !== -1) {
			el.text = el.text.split(oldName).join(newName);
		}
	});

};

var removeTinyMCEs = function(element) {

	element = $(element);

	var editors = window.tinymce ? window.tinymce.editors || [] : [];
	var textarea, textareas;
	for (var i = editors.length - 1; i >= 0; i--) {
		textarea = editors[i].getElement();
		if (element.contains(textarea)) {
			textareas = element.retrieve('rsce_tinyMCE_textareas', []);
			textareas.push({
				textarea: textarea,
				settings: Object.append({}, editors[i].settings)
			});
			element.store('rsce_tinyMCE_textareas', textareas);
			editors[i].remove();
		}
	}

};

var restoreTinyMCEs = function(element) {

	element = $(element);

	if (window.tinymce && window.tinymce.Editor) {
		element.retrieve('rsce_tinyMCE_textareas', []).each(function(data) {
			new window.tinymce.Editor(
				data.textarea.get('id'),
				data.settings,
				window.tinymce.EditorManager
			).render();
		});
		element.store('rsce_tinyMCE_textareas', []);
	}

};

var restoreChosens = function(element) {

	$(element).getElements('.chzn-container').each(function(container) {
		var select = container.getPrevious('select');
		if (!select) {
			return;
		}
		select.setStyle('display', '').removeClass('chzn-done');
		container.destroy();
		$$([select]).chosen();
	});

};

var updateListButtons = function(listElement) {

	listElement = $(listElement);

	var allItems = listElement.getChildren('.rsce_list_inner')[0].getChildren('.rsce_list_item');
	var count = allItems.length;
	var config = listElement.retrieve('rsce_config', {});
	var minReached = !!(config.minItems && count <= config.minItems);
	var maxReached = !!(
		typeof config.maxItems === 'number'
		&& count >= config.maxItems
	);

	listElement.getChildren('.rsce_list_toolbar')[0].getFirst('.header_new').setStyle(
		'display',
		maxReached ? 'none' : ''
	);

	allItems.each(function(el, index) {
		var toolbar = el.getChildren('.rsce_list_toolbar')[0];
		toolbar.getFirst('.rsce_list_toolbar_up').setStyle(
			'display',
			!index ? 'none' : ''
		);
		toolbar.getFirst('.rsce_list_toolbar_down').setStyle(
			'display',
			index === count - 1 ? 'none' : ''
		);
		toolbar.getFirst('.rsce_list_toolbar_drag').setStyle(
			'display',
			count < 2 ? 'none' : ''
		);
		toolbar.getFirst('.rsce_list_toolbar_delete').setStyle(
			'display',
			minReached ? 'none' : ''
		);
		toolbar.getFirst('.rsce_list_toolbar_new').setStyle(
			'display',
			maxReached ? 'none' : ''
		);
	});

};

var initListSort = function(listInner) {

	if (!listInner.getElements('.drag-handle').length) {
		return;
	}

	var ds = new Scroller(document.body, {
		onChange: function(x, y) {
			this.element.scrollTo(this.element.getScroll().x, y);
		}
	});

	listInner.retrieve('listSort', new Sortables(listInner, {
		contstrain: true,
		opacity: 0.6,
		handle: '.drag-handle',
		onStart: function(element) {
			removeTinyMCEs(element);
			ds.start();
		},
		onComplete: function() {
			ds.stop();
			listInner.getChildren('.rsce_list_item').each(function(el) {
				removeTinyMCEs(el);
			});
			listInner.getChildren('.rsce_list_item').each(function(el) {
				renameElement(el);
			});
			listInner.getChildren('.rsce_list_item').each(function(el) {
				restoreTinyMCEs(el);
			});
			updateListButtons(listInner.getParent('.rsce_list'));
			restoreChosens(listInner);
		}
	}));

};

var newElementAtPosition = function(listElement, position) {

	listElement = $(listElement);
	var config = listElement.retrieve('rsce_config', {});

	var dummyItem = listElement
		.getChildren('.rsce_list_item.rsce_list_item_dummy')[0];
	var listInner = listElement.getChildren('.rsce_list_inner');
	if (!listInner.length) {
		listInner = new Element('div', {'class': 'rsce_list_inner'})
			.inject(listElement);
	}
	else {
		listInner = listInner[0];
	}
	var allItems = listInner.getChildren('.rsce_list_item');

	if (
		typeof config.maxItems === 'number'
		&& allItems.length >= config.maxItems
	) {
		return;
	}

	var newItem = new Element('div', {'class': 'rsce_list_item'});

	allItems.each(function(el) {
		removeTinyMCEs(el);
	});
	removeTinyMCEs(dummyItem);

	var key = dummyItem.get('data-rsce-name')
		.substr(0, dummyItem.get('data-rsce-name').length - 12);
	var newKey = key + '__' + position;
	var newFields = [];

	newItem.set('data-rsce-name', newKey);

	var newItemHtml = dummyItem.get('html')
		.split(' data-rsce-required="data-rsce-required"')
		.join(' required="required"')
		.split(key + '__rsce_dummy')
		.join(newKey);
	newItem.set('html', newItemHtml);

	var newItemTextareas = [];
	dummyItem.retrieve('rsce_tinyMCE_textareas', []).each(function(data) {
		var newData = {
			settings: Object.append({}, data.settings)
		};
		// Get textarea by id does not work here
		newItem.getElements('textarea').each(function(el) {
			if (el.get('id') === data.textarea.get('id').split(key + '__rsce_dummy').join(newKey)) {
				newData.textarea = el;
			}
		});
		if (newData.textarea) {
			newItemTextareas.push(newData);
		}
	});
	newItem.store('rsce_tinyMCE_textareas', newItemTextareas);

	newItem.getChildren('[data-rsce-label]').each(function(el) {
		el.set(
			'text',
			el.get('data-rsce-label').split('%s').join(position + 1)
		);
	});

	newItem.getElements('.sortable.sortable-done').removeClass('sortable-done');

	if (position) {
		newItem.inject(allItems[position - 1], 'after');
	}
	else {
		newItem.inject(listInner, 'top');
	}

	newItem.getElements('[name^="' + newKey + '"]').each(function(input) {
		if (
			input.getParent('.rsce_list_item') === newItem &&
			input.get('name').indexOf('__rsce_dummy') === -1
		) {
			newFields.push(input.get('name').split('[')[0]);
		}
	});

	newItem.getElements('[data-rsce-title]').each(function(el) {
		el.set('title', el.get('data-rsce-title'));
	});

	restoreChosens(newItem);

	newItem.grab(new Element('input', {
		type: 'hidden',
		name: 'FORM_FIELDS[]',
		value: newFields.join(',')
	}));

	newItem.getAllNext('.rsce_list_item').each(function(el) {
		renameElement(el);
		restoreChosens(el);
	});

	newItem.getElements('.rsce_list').each(function(el) {
		initList(el);
	});

	allItems.each(function(el) {
		restoreTinyMCEs(el);
	});
	restoreTinyMCEs(newItem);

	newItemHtml.replace(/<script[^>]*>([\s\S]*?)<\/script>/gi, function(all, code){

		code = code.replace(/<!--|\/\/-->|<!\[CDATA\[\/\/>|<!\]\]>/g, '');

		// Ignore tinyMCEs
		if (/^\s*window\.tinymce\s*&&\s*tinymce.init\s*\(/.test(code)) {
			return '';
		}

		try {
			Browser.exec(code);
		}
		catch(e) {}

		return '';

	});

	if (listInner.retrieve('listSort')) {
		listInner.retrieve('listSort').addItems(newItem);
	}
	else {
		initListSort(listInner);
	}

	updateListButtons(listElement);

	try {
		window.fireEvent('subpalette');
	}
	catch(e) {}

	try {
		window.fireEvent('ajax_change');
	}
	catch(e) {}

};

var newElement = function(linkElement) {

	var listElement = $(linkElement).getParent('.rsce_list');

	return newElementAtPosition(listElement, 0);

};

var newElementAfter = function(linkElement) {

	var listElement = $(linkElement).getParent('.rsce_list');
	var position = $(linkElement).getParent('.rsce_list_item')
		.getAllPrevious('.rsce_list_item').length + 1;

	return newElementAtPosition(listElement, position);

};

var deleteElement = function(linkElement) {

	var element = $(linkElement).getParent('.rsce_list_item');
	var listElement = element.getParent('.rsce_list');
	var listInner = element.getParent('.rsce_list_inner');
	var allItems = listInner.getChildren('.rsce_list_item');
	var nextElements = element.getAllNext('.rsce_list_item');

	var config = listElement.retrieve('rsce_config', {});

	if (config.minItems && allItems.length <= config.minItems) {
		return;
	}

	removeTinyMCEs(element);
	if (listInner.retrieve('listSort')) {
		listInner.retrieve('listSort').removeItems(element);
	}
	element.destroy();
	nextElements.each(function(nextElement) {
		removeTinyMCEs(nextElement);
	});
	nextElements.each(function(nextElement) {
		renameElement(nextElement);
	});
	nextElements.each(function(nextElement) {
		restoreChosens(nextElement);
		restoreTinyMCEs(nextElement);
	});

	updateListButtons(listElement);

	$(document.body).getChildren('.tip-wrap').each(function(el) {
		el.dispose();
	});
	setTimeout(function() {
		$(document.body).getChildren('.tip-wrap').each(function(el) {
			el.dispose();
		});
	}, 1000);

};

var moveElement = function(linkElement, offset) {

	var element = $(linkElement).getParent('.rsce_list_item');
	var swapElement;
	if (offset > 0) {
		swapElement = element.getNext('.rsce_list_item');
	}
	else if (offset < 0) {
		swapElement = element.getPrevious('.rsce_list_item');
	}
	if (!swapElement) {
		return;
	}

	// The order is important to prevent id conflicts:
	// remove tinyMCEs => move the element => rename => restoring tinyMCEs

	removeTinyMCEs(swapElement);
	removeTinyMCEs(element);

	element.inject(swapElement, offset > 0 ? 'after' : 'before');

	renameElement(swapElement);
	renameElement(element);

	restoreChosens(swapElement);
	restoreChosens(element);

	restoreTinyMCEs(swapElement);
	restoreTinyMCEs(element);

	updateListButtons(element.getParent('.rsce_list'));

};

var removeFormFields = function(fields, input) {
	if (!fields || !fields.length || !input) {
		return;
	}
	input = $(input);
	var value = input.get('value');
	fields.each(function(field) {
		value = value.split(',' + field + ',').join(',');
	});
	input.set('value', value);
};

var initList = function(listElement) {

	listElement = $(listElement);

	if (listElement.get('id').indexOf('__rsce_dummy__') !== -1) {
		return;
	}

	if (listElement.getChildren('.rsce_list_inner').length) {
		// Already initialized
		return;
	}

	if (listElement.get('data-config')) {
		listElement.store(
			'rsce_config',
			JSON.decode(listElement.get('data-config'))
		);
	}

	var listInner = new Element('div', {'class': 'rsce_list_inner'})
		.inject(
			listElement.getChildren('.rsce_list_item.rsce_list_item_dummy')[0],
			'after'
		);

	listElement.getChildren('.rsce_list_item').each(function(element) {

		if (element.hasClass('rsce_list_item_dummy')) {
			return;
		}

		var key = element.get('data-rsce-name');
		var fields = [];

		element.getElements('[name^="' + key + '"]').each(function(input) {
			if (
				input.getParent('.rsce_list_item') === element &&
				input.get('name').indexOf('__rsce_dummy') === -1
			) {
				fields.push(input.get('name').split('[')[0]);
			}
		});

		element.grab(new Element('input', {
			type: 'hidden',
			name: 'FORM_FIELDS[]',
			value: fields.join(',')
		}));

		removeFormFields(
			fields,
			element.getParent('form')
				.getElements('input[name="FORM_FIELDS[]"]')[0]
		);

		element.inject(listInner);

	});

	var dummyFields = [];
	listElement.getElements('[name*="__rsce_dummy__"]').each(function(input) {
		if (input.required) {
			input.required = false;
			input.setProperty('data-rsce-required', 'data-rsce-required');
		}
		dummyFields.push(input.get('name').split('[')[0]);
	});
	removeFormFields(
		dummyFields,
		listElement.getParent('form')
			.getElements('input[name="FORM_FIELDS[]"]')[0]
	);

	initListSort(listInner);

	updateListButtons(listElement);

};

// public objects
window.rsceNewElement = newElement;
window.rsceNewElementAfter = newElementAfter;
window.rsceDeleteElement = deleteElement;
window.rsceMoveElement = moveElement;
window.rsceInitList = initList;

})(document.id, window);
