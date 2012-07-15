(function($){
	// Updates the input value and preview to the given color for the current selector group
	var pickColor = function(color) {
		$(current_selector_group).find('.vehicle-info-color-selector-input').val(color);
		$(current_selector_group).find('.vehicle-info-color-selector-preview').css('background-color', color);
	};

	// Stores each Farbtastic instance
	var farbs = new Object;

	// Stores what color setting is currently being modified
	var current_selector_group;

	$(document).ready( function() {
		$('.vehicle-info-color-selector').each( function() {
			var input = $(this).find('.vehicle-info-color-selector-input');
			var name = $(input).attr('name');

			farbs[name] = $.farbtastic( $(this).find('.vehicle-info-color-selector-picker'), pickColor );
			farbs[name].setColor( $(input).val() );
		});

		// Open the picker when clicking the button or preview
		$('.vehicle-info-color-selector-button, .vehicle-info-color-selector-preview').click( function(e) {
			current_selector_group = $(this).parent('.vehicle-info-color-selector');
			$(this).siblings('.vehicle-info-color-selector-picker').show();
			e.preventDefault();
		});

		// Change the color when typing into the input
		$('.vehicle-info-color-selector-input').keyup( function() {
			current_selector_group = $(this).parent('.vehicle-info-color-selector');
			var color = $(this).val();
			pickColor( color );

			// If it's a format Farbtastic will understand, update the picker
			if ( '#' == color.substr( 0, 1 ) )
				farbs[$(this).attr('name')].setColor(color);
		});

		// Close pickers on click-away
		$(document).mousedown( function() {
			$('.vehicle-info-color-selector-picker').hide();
		});
	});
})(jQuery);