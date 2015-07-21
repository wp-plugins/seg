jQuery(function($) {
	var importConsole = $('.seg-import-screen');

	$('.seg-have-account').click(function() {
		$('#seg-content-1').slideUp();
		$('#seg-content-2').slideDown();
	});

	$('.seg-start-import').on('click', function() {
		var button = $(this);
		
		importConsole.empty();
		importConsole.show();
		importConsole.data( "import_orders_total", 0);
		importConsole.data( "import_orders_done", 0);
		importConsole.data( "import_customers_total", 0);
		importConsole.data( "import_customers_done", 0);

		import_customers();
		
		return false;
	});


/* =========================== Import customers =========================== */
/* =========================== Import customers =========================== */
/* =========================== Import customers =========================== */

	function import_customers() {
		$.ajax({
			type: "POST",
			url: seg_js.ajax,
			data: {'action': 'seg_get_all_customers'},
			cache: false,
			timeout: 30000,
			beforeSend: customers_listed_start,
			success: customers_listed,
			error: customers_listed_error
		});
	}

	function customers_listed_start() {
		importConsole.append("<p style='color:green;font-weight:bold'>Getting list of customers to import...");
		importConsole_scroll();
	}

	function customers_listed(msg) {
		var obj = jQuery.parseJSON(msg);
		importConsole.append("<p>" + obj.message + "</p>");
		importConsole_scroll();
		
		if (obj.success == 1 && obj.customers) {
			var customers = obj.customers;

			importConsole.data("import_customers_total", customers.length);
			
			import_next_customer(customers);
		}
	}

	function import_next_customer(customers) {
		var index = parseInt(importConsole.data('import_customers_done'));

		if (index >= customers.length) {
			import_orders();
		} else {
			$.ajax({
				type: "POST",
				url: seg_js.ajax,
				cache: false,
				timeout: 30000,
				data: {'action': 'seg_import_customer_id', 'id' : customers[index]},
				complete: function(msg) {
					customer_imported(msg);
					import_next_customer(customers);
				}
			});
		}
	}

	function customer_imported(msg) {
		var obj = jQuery.parseJSON(msg.responseText);
		var import_customers_done  = importConsole.data("import_customers_done") + 1;

		importConsole.data("import_customers_done", import_customers_done);
		importConsole.append('<div>' + obj.message + ' <em style="margin-left:10px">(' + importConsole.data("import_customers_done") + '/' + importConsole.data("import_customers_total") + ')</div>');
		importConsole_scroll();
	}

	function customers_listed_error() {
		importConsole.append('<p style="color:red;font-weight:bold">Error getting customers to import. Please try again in a few moments.</p>');
		importConsole_scroll();
	}

/* =========================== Import orders =========================== */
/* =========================== Import orders =========================== */
/* =========================== Import orders =========================== */

	function import_orders() {
		$.ajax({
			type: "POST",
			url: seg_js.ajax,
			data: {'action': 'seg_get_all_orders'},
			cache: false,
			timeout: 30000,
			beforeSend: orders_listed_start,
			success: orders_listed,
			error: orders_listed_error
		});
	}

	function orders_listed_start() {
		importConsole.append("<p style='color:green;font-weight:bold'>Getting list of orders to import...</p>");
		importConsole_scroll();
	}

	function orders_listed(msg) {
		var obj = jQuery.parseJSON(msg);
		importConsole.append("<p>" + obj.message + "</p>");
		importConsole_scroll();
		
		if (obj.success == 1 && obj.orders) {
			var orders = obj.orders;

			importConsole.data("import_orders_total", orders.length);

			import_next_order(orders);
		}
	}

	function import_next_order(orders) {
		var index = parseInt(importConsole.data('import_orders_done'));

		if (index >= orders.length) {
			$.ajax({
				type: "POST",
				url: seg_js.ajax,
				data: {'action': 'seg_import_finish'},
				cache: false,
				timeout: 30000,
				success: function (msg) { 
					var obj = jQuery.parseJSON(msg);
					importConsole.append('<p style="color:green;font-weight:bold">' + obj.message + '</p>');
					importConsole_scroll();
				}
			});
		} else {
			$.ajax({
				type: "POST",
				url: seg_js.ajax,
				cache: false,
				timeout: 30000,
				data: {'action': 'seg_import_order_id', 'id' : orders[index]},
				complete: function(msg) {
					order_imported(msg);
					import_next_order(orders);
				}
			});
		}
	}

	function order_imported(msg) { 
		var obj = jQuery.parseJSON(msg.responseText);
		var import_orders_done  = $( '.seg-import-screen').data( "import_orders_done") + 1;

		importConsole.data("import_orders_done", import_orders_done);
		importConsole.append('<div>' + obj.message + ' <em style="margin-left:10px">(' + $( '.seg-import-screen').data("import_orders_done") + '/' + $( '.seg-import-screen').data("import_orders_total") + ')</div>');
		importConsole_scroll();
	}

	function orders_listed_error() {
		importConsole.append('<p style="color:red;font-weight:bold">Error getting orders to import. Please try again in a few moments. </p>');
		importConsole_scroll();
	}

	function importConsole_scroll() {
		importConsole.scrollTop(importConsole[0].scrollHeight);
	}
});
