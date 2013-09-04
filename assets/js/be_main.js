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

// public objects
window.RsceBackend = RsceBackend;

})(document.id, window);
