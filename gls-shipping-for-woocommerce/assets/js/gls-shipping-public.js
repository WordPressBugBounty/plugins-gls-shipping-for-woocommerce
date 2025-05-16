/* eslint-disable */
(function () {
	"use strict";

	function domReady(callback) {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", callback);
		} else {
			callback();
		}
	}

	domReady(function () {
		var mapElements = document.getElementsByClassName("inchoo-gls-map");

		if (mapElements.length > 0) {
			for (var i = 0; i < mapElements.length; i++) {
				mapElements[i].addEventListener("change", function (e) {
					var pickupInfo = e.detail;
					var pickupInfoDiv =
						document.getElementById("gls-pickup-info");
					if (pickupInfoDiv) {
						pickupInfoDiv.innerHTML =
							"<strong>" +
							gls_croatia.pickup_location +
							":</strong><br>" +
							gls_croatia.name +
							": " +
							pickupInfo.name +
							"<br>" +
							gls_croatia.address +
							": " +
							pickupInfo.contact.address +
							", " +
							pickupInfo.contact.city +
							", " +
							pickupInfo.contact.postalCode +
							"<br>" +
							gls_croatia.country +
							": " +
							pickupInfo.contact.countryCode;
						pickupInfoDiv.style.display = "block";
					}

					var hiddenInput = document.getElementById(
						"gls-pickup-info-data"
					);
					if (!hiddenInput) {
						hiddenInput = document.createElement("input");
						hiddenInput.type = "hidden";
						hiddenInput.id = "gls-pickup-info-data";
						hiddenInput.name = "gls_pickup_info";
						document.forms["checkout"].appendChild(hiddenInput);
					}
					hiddenInput.value = JSON.stringify(pickupInfo);
				});
			}
		}

		function showMapModal(mapClass) {
			var selectedCountry =
				document.getElementById("billing_country").value;
			var mapElement = document.querySelector("." + mapClass);
			mapElement.setAttribute("country", selectedCountry.toLowerCase());
			mapElement.showModal();
		}

		document.body.addEventListener("click", function (event) {
			if (
				event.target.matches(".dugme-gls_shipping_method_parcel_locker")
			) {
				showMapModal("gls-map-locker");
			} else if (
				event.target.matches(".dugme-gls_shipping_method_parcel_shop")
			) {
				showMapModal("gls-map-shop");
			}
		});

		function clearGLSPickupInfo() {
			var glsPickupInfo = document.getElementById("gls-pickup-info");
			var glsPickupInfoData = document.getElementById(
				"gls-pickup-info-data"
			);

			if (glsPickupInfo) {
				glsPickupInfo.innerHTML = "";
				glsPickupInfo.style.display = "none";
			}
			if (glsPickupInfoData) {
				glsPickupInfoData.value = "";
			}
		}

		function updateCheckout() {
			var selectedShippingMethod = document.querySelector(
				'input[name="shipping_method[0]"]:checked'
			);
			var glsMap = document.getElementById("gls-map");

			clearGLSPickupInfo();

			if (selectedShippingMethod) {
				switch (selectedShippingMethod.value) {
					case "gls_shipping_method_parcel_locker":
						glsMap.setAttribute("filter-type", "parcel-locker");
						break;
					case "gls_shipping_method_parcel_shop":
						glsMap.setAttribute("filter-type", "parcel-shop");
						break;
				}
			}
		}

		// Event listener for shipping method change
		document.body.addEventListener("change", function (event) {
			if (event.target.name === "shipping_method[0]") {
				updateCheckout();
			}
		});

		document.body.addEventListener("updated_checkout", updateCheckout);
	});
})();
