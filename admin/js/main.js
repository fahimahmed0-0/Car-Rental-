$(document).ready(function () {
	// Sidebar menu functionality
	$(".ts-sidebar-menu li a").each(function () {
		if ($(this).next().length > 0) {
			$(this).addClass("parent");
		};
	})
	var menux = $('.ts-sidebar-menu li a.parent');
	$('<div class="more"><i class="fa fa-angle-down"></i></div>').insertBefore(menux);
	$('.more').click(function () {
		$(this).parent('li').toggleClass('open');
	});
	$('.parent').click(function (e) {
		e.preventDefault();
		$(this).parent('li').toggleClass('open');
	});

	// Mobile menu toggle
	$('.menu-btn').click(function (e) {
		e.preventDefault();
		$('nav.ts-sidebar').toggleClass('menu-open');
		$(this).toggleClass('active');
	});

	// Close menu when clicking outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.ts-sidebar, .menu-btn').length) {
			$('nav.ts-sidebar').removeClass('menu-open');
			$('.menu-btn').removeClass('active');
		}
	});

	// DataTable initialization with responsive configuration
	if ($.fn.DataTable) {
		$('#zctb').DataTable({
			responsive: true,
			scrollX: true,
			autoWidth: false
		});
	}

	// File input initialization
	if ($("#input-43").length) {
		$("#input-43").fileinput({
			showPreview: false,
			allowedFileExtensions: ["zip", "rar", "gz", "tgz"],
			elErrorContainer: "#errorBlock43"
		});
	}
});
