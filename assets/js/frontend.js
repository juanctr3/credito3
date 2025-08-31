jQuery(document).ready(function($) {
    // Lógica del acordeón para mostrar/ocultar los detalles del plan
    $('.wcps-toggle-details').on('click', function(e) {
        // Prevenir que el clic en el '+' active la selección del radio button
        e.preventDefault(); 
        
        var $details = $(this).closest('.wcps-plan').find('.wcps-plan-details');
        $details.slideToggle();
        $(this).text($(this).text() === '+' ? '-' : '+');
    });
});
