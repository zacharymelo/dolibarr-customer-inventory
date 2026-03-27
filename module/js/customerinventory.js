/**
 * Customer Inventory module - tooltip dismiss handler
 */
document.addEventListener('DOMContentLoaded', function() {
	var dismissBtn = document.getElementById('ci-tooltip-dismiss');
	if (dismissBtn) {
		dismissBtn.addEventListener('click', function(e) {
			e.preventDefault();
			var tooltip = document.getElementById('ci-tooltip');
			if (tooltip) {
				tooltip.style.display = 'none';
			}

			// AJAX call to persist dismissal server-side
			var token = (typeof ci_csrf_token !== 'undefined') ? ci_csrf_token : '';
			var xhr = new XMLHttpRequest();
			xhr.open('POST', 'ajax/dismiss_tooltip.php?token=' + encodeURIComponent(token), true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send('action=dismiss');
		});
	}
});
