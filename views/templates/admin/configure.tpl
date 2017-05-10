{*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
	<h3><i class="icon icon-credit-card"></i> {l s='productostb' mod='productostb'}</h3>
	<p>
		<!-- <a href="{$enlace_sincronizar}">Sincronizar</a> -->
		<button id="sincronizar">{l s='Sincronizar' mod='productostb'}</button>
	</p>
	<div id="spinner"><img src="{$module_dir}img/spinner.gif" width="64" height="64"></div>
	<div id="log"></div>
	<div id="enlace_log"><a href="{$enlace_log}">{l s='Descargar log' mod='productostb'}</a></div>
</div>

<script type="text/javascript">
	function displayLog(msg){
		var texto = $("#log").html();
		$("#log").html(msg + "<br />" + texto);
	}

	$("button#sincronizar").click(function(event) {
		$("#spinner").show();
		$("#log").html("");
		displayLog("Procesando archivo XML");
		$.ajax({
			url: '{$enlace_getproducts}',
			type: 'GET',
			dataType: 'json',
			async: false,
		})
		.done(function(data) {
			displayLog("Archivo XML procesado");
			displayLog("Comenzando actualización de productos");
			if(data.success){
				var prod_nbr = data.productos.length;
				var cont = 0;
				$.each(data.productos, function(index, producto) {
					cont = cont + 1;
					$.ajax({
						url: '{$enlace_sincronizar}',
						type: 'POST',
						dataType: 'json',
						async: false,
						data: {
							producto: JSON.stringify(producto),
							nbr: prod_nbr,
							cont: cont,
						},
					})
					.done(function(res) {
						displayLog(res.msg);
					});
				});
			}
		});
		$("#spinner").hide();
	});
	
</script>
