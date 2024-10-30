(function( $ ) {
	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	/**
	 *
	 * HTTP/s $_GET
	 *
	 */
	function mbemexGET(name, url) {
		if (!url) url = window.location.href;
		name = name.replace(/[\[\]]/g, '\\$&');
		var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
			results = regex.exec(url);
		if (!results) return null;
		if (!results[2]) return '';
		return decodeURIComponent(results[2].replace(/\+/g, ' '));
	}

	function isNumberKey(evt){
		var charCode = (evt.which) ? evt.which : event.keyCode

		console.log(charCode);

		if (charCode > 31 && (charCode < 48 || charCode > 57 || charCode == 16)){

			event.preventDefault(); 
		}

		return true;
	}

	// MARK: Redirect to mbemex tab on ingration only
	function mbemexRedirectToMbeShippingTab(){
		if(mbemexGET('section') == null && mbemexGET('tab') == 'integration'){
			document.location.href = window.location.href + '&section=mbemex';
		}
	}

	// MARK: Add Valid number for input
	function onlyNumberForPercentShipping(){
		$(document).on("keydown keypress",".mbemex-only-number",function(event){
            isNumberKey(event)
        });
	}

	function showEditInputs(){
		$(document).on('click','.mbemex-edit',function(){
			var show = $(this).data('show');
			var hide = $(this).data('hide');

			$('.'+hide).hide();
			$('.'+show).show();

			return false;
		});
	}

	// Remove parent
	function removeHiddenParent(){
		$('.mbemex-remove-tr-parent').parent('fieldset').closest('tr').remove();
		$('.mbemex-hidden-parent').parent('fieldset').closest('tr').hide();
	}

	// Set Table services
	function setTableServices(){
		if ($('.mbemex-set-table-services').length) {
			var html = '';

			if ($('#woocommerce_mbemex_token').val() == '') {
				html += '<div class="notice notice-error" style="max-width:602px;padding:20px;">';
					html += '<strong>';
						html += '<span class="dashicons dashicons-info mbemex-color-danger"></span> ';
						html += 'Debe escribir el token de integración para desplegar los servicios de envío';
					html += '</strong>';
				html += '</div>';
			}
			else{

				var services = $('.mbemex-data-services').data('ser');

				html += '<table class="wc_emails widefat" cellspacing="0" width="100%" style="max-width:634px;">';
					html += '<thead>';
						html += '<tr>';
							html += '<th><strong>Servicio</strong></th>';
							html += '<th><strong>Precio del envío</strong></th>';
							html += '<th><strong>Porcentaje o precio fijo</strong></th>';
						html += '</tr>';
					html += '</thead>';

					html += '<tbody>';
						for (var i = 0; i < services.length; i++) {
							html += '<tr>';
								html += '<td>'+services[i].service+' (MSI: '+services[i].id+')</td>';
								
								html += '<td>';
									html += '<select class="mbemex-sel-ship select" name="woocommerce_mbemex_calculationtype'+services[i].id+'" id="woocommerce_mbemex_calculationtype'+services[i].id+'" style="width:100%;" data-id="'+services[i].id+'">';
										
										var selected = "";
										if(services[i].caltypesel == "equal") selected = "selected";
										html += '<option value="equal" '+selected+'>Dejar igual</option>';

										var selected = "";
										if(services[i].caltypesel == "up") selected = "selected";
										html += '<option value="up" '+selected+'>Aumentar</option>';

										var selected = "";
										if(services[i].caltypesel == "down") selected = "selected";
										html += '<option value="down" '+selected+'>Disminuir</option>';

										var selected = "";
										if(services[i].caltypesel == "fixed") selected = "selected";
										html += '<option value="fixed" '+selected+'>Precio fijo</option>';
									html += '</select>';
								html += '</td>';
								
								html += '<td>';
									html += '<input class="input-text regular-input mbemex-only-number" type="text" name="woocommerce_mbemex_shippingpercent'+services[i].id+'" id="woocommerce_mbemex_shippingpercent'+services[i].id+'" style="width:100%;" value="'+services[i].shipcent+'" >';

									if (services[i].caltypesel == 'up' || services[i].caltypesel == 'down') {
										html += '<small class="mbemex-note-'+services[i].id+'">Se calcula en %</small>';
									}
									else{
										html += '<small class="mbemex-note-'+services[i].id+'" style="display:none;">Se calcula en %</small>';
									}
								html += '</td>';
							html += '</tr>';
						}
					html += '</tbody>';
				html += '</table>';
			}

			$('.mbemex-set-table-services').after(html);

			$(document).on('change','.mbemex-sel-ship',function(){
				var id = $(this).data('id');
				var value = $(this).val();
				
				if (value == 'up' || value == 'down') {
					$('.mbemex-note-'+id).show();
				}
				else{
					$('.mbemex-note-'+id).hide();
				}
			});
		}
	}

	$(function() {
		//mbemexRedirectToMbeShippingTab();
		onlyNumberForPercentShipping();
		showEditInputs();
		setTableServices();
		removeHiddenParent();
	});

	$(window).load(function(){
		
	});

})( jQuery );