;(function($, window) {

var RsceBackend = {

	openModalSelector: function(options) {
		var opt = options || {};
		var max = (window.getSize().y-180).toInt();
		if (!opt.height || opt.height > max) opt.height = max;
		var M = new SimpleModal({
			'width': opt.width,
			'btn_ok': Contao.lang.close,
			'draggable': false,
			'overlayOpacity': .5,
			'onShow': function() { document.body.setStyle('overflow', 'hidden'); },
			'onHide': function() { document.body.setStyle('overflow', 'auto'); }
		});
		M.addButton(Contao.lang.close, 'btn', function() {
			this.hide();
		});
		M.addButton(Contao.lang.apply, 'btn primary', function() {
			var val = [],
				frm = null,
				frms = window.frames;
			for (var i=0; i<frms.length; i++) {
				if (frms[i].name == 'simple-modal-iframe') {
					frm = frms[i];
					break;
				}
			}
			if (frm === null) {
				alert('Could not find the SimpleModal frame');
				return;
			}
			if (frm.document.location.href.indexOf('contao/main.php') != -1) {
				alert(Contao.lang.picker);
				return; // see #5704
			}
			var inp = frm.document.getElementById('tl_listing').getElementsByTagName('input');
			for (var i=0; i<inp.length; i++) {
				if (!inp[i].checked || inp[i].id.match(/^check_all_/)) continue;
				if (!inp[i].id.match(/^reset_/)) val.push(inp[i].get('value'));
			}
			if (opt.tag) {
				$(opt.tag).value = val.join(',');
				if (opt.url.match(/page\.php/)) {
					$(opt.tag).value = '{{link_url::' + $(opt.tag).value + '}}';
				}
				opt.self.set('href', opt.self.get('href').replace(/&value=[^&]*/, '&value='+val.join(',')));
			} else {
				$('ctrl_'+opt.id).value = val.join("\t");
				var act = (opt.url.indexOf('contao/page.php') != -1) ? 'rsceReloadPagetree' : 'rsceReloadFiletree';
				new Request.Contao({
					field: $('ctrl_'+opt.id),
					evalScripts: false,
					onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
					onSuccess: function(txt, json) {
						$('ctrl_'+opt.id).getParent('div').set('html', json.content);
						json.javascript && Browser.exec(json.javascript);
						AjaxRequest.hideBox();
						window.fireEvent('ajax_change');
					}
				}).post({'action':act, 'name':opt.id, 'value':$('ctrl_'+opt.id).value, 'REQUEST_TOKEN':Contao.request_token});
			}
			this.hide();
		});
		M.show({
			'title': opt.title,
			'contents': '<iframe src="' + opt.url + '" name="simple-modal-iframe" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
			'model': 'modal'
		});
	}

};

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

};

var initListSort = function(listInner) {

	var ds = new Scroller(document.body, {
		onChange: function(x, y) {
			this.element.scrollTo(this.element.getScroll().x, y);
		}
	});

	var sort = listInner.retrieve('listSort', new Sortables(listInner, {
		contstrain: true,
		opacity: 0.6,
		handle: '.drag-handle',
		onStart: function() {
			ds.start();
		},
		onComplete: function() {
			ds.stop();
			listInner.getChildren('.rsce_list_item').each(function(el) {
				renameElement(el);
			});
		}
	}));

};

var newElementAtPosition = function(listElement, position) {

	var dummyItem = $(listElement)
		.getChildren('.rsce_list_item.rsce_list_item_dummy')[0];
	var listInner = $(listElement).getChildren('.rsce_list_inner');
	if (!listInner.length) {
		listInner = new Element('div', {'class': 'rsce_list_inner'})
			.inject(listElement);
		initListSort(listInner);
	}
	else {
		listInner = listInner[0];
	}
	var allItems = listInner.getChildren('.rsce_list_item');
	var newItem = new Element('div', {'class': 'rsce_list_item'});

	var key = dummyItem.get('data-rsce-name')
		.substr(0, dummyItem.get('data-rsce-name').length - 12);
	var newKey = key + '__' + position;
	var newFields = [];

	newItem.set('data-rsce-name', newKey);
	newItem.set('html', dummyItem.get('html')
		.split(key + '__rsce_dummy')
		.join(newKey));

	newItem.getChildren('[data-rsce-label]').each(function(el) {
		el.set(
			'text',
			el.get('data-rsce-label').split('%s').join(position + 1)
		);
	});

	if (position) {
		newItem.inject(allItems[position - 1], 'after');
	}
	else {
		newItem.inject(listInner, 'top');
	}

	newItem.getElements('[name^="' + newKey + '"]').each(function(input) {
		if (input.get('name').indexOf('__rsce_dummy') === -1) {
			newFields.push(input.get('name').split('[')[0]);
		}
	});

	newItem.getElements('[data-rsce-title]').each(function(el) {
		el.set('title', el.get('data-rsce-title'));
	});

	newItem.grab(new Element('input', {
		type: 'hidden',
		name: 'FORM_FIELDS[]',
		value: newFields.join(',')
	}));

	newItem.getAllNext('.rsce_list_item').each(function(el) {
		renameElement(el);
	});

	listInner.retrieve('listSort').addItems(newItem);

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
	var listInner = element.getParent('.rsce_list_inner');
	var nextElements = element.getAllNext('.rsce_list_item');
	listInner.retrieve('listSort').removeItems(element);
	element.destroy();
	nextElements.each(function(nextElement) {
		renameElement(nextElement);
	});

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

	element.inject(swapElement, offset > 0 ? 'after' : 'before');
	renameElement(swapElement);
	renameElement(element);

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

	if ($(listElement).get('id').indexOf('__rsce_dummy__') !== -1) {
		return;
	}

	var listInner = new Element('div', {'class': 'rsce_list_inner'})
		.inject(listElement);

	$(listElement).getChildren('.rsce_list_item').each(function(element) {

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

	initListSort(listInner);

};

// public objects
window.RsceBackend = RsceBackend;
window.rsceNewElement = newElement;
window.rsceNewElementAfter = newElementAfter;
window.rsceDeleteElement = deleteElement;
window.rsceMoveElement = moveElement;
window.rsceInitList = initList;

})(document.id, window);
