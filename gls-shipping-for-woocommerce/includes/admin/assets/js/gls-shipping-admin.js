(function () {
	jQuery(document).ready(function ($) {
		// Generating label in order details page
		$(".gls-print-label").on("click", function () {
			const orderId = $(this).attr("order-id");
			const $button = $(this);
			const count = $("#gls_label_count").val() || 1;
			$button.prop("disabled", true);
			generateGLSLabel(orderId, $button, count);
		});

		// Generate label in order listing page
		$("a.gls-generate-label").on("click", function (e) {
			e.preventDefault();
			const orderId = $(this)
				.closest("tr")
				.find(".check-column input")
				.val();
			const $button = $(this);
			$button.addClass("disabled");
			generateGLSLabel(orderId, $button, 1);
		});

		function generateGLSLabel(orderId, $button, count) {
			$.ajax({
				url: gls_croatia.adminAjaxUrl,
				type: "POST",
				data: {
					action: "gls_generate_label",
					orderId: orderId,
					postNonce: gls_croatia.ajaxNonce,
					count: count,
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(
							"Error generating GLS Label: " + response.data.error
						);
					}
				},
				error: function () {
					alert("An error occurred while generating the GLS Label.");
				},
				complete: function () {
					// Re-enable the button
					if ($button.hasClass("gls-print-label")) {
						$button.prop("disabled", false);
					} else {
						$button.removeClass("disabled");
					}
				},
			});
		}
	});
})(jQuery);
