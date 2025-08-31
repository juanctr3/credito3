jQuery(document).ready(function($) {
    console.log('WCPS Frontend cargado correctamente');
    
    // Verificar que los planes existen en la página
    var $plansContainer = $('.wcps-plans-container');
    if ($plansContainer.length > 0) {
        console.log('Contenedor de planes encontrado');
        
        // Verificar radios existentes
        var $radios = $('input[name="wcps_selected_plan"]');
        console.log('Radios encontrados:', $radios.length);
    }
    
    // Lógica del acordeón para mostrar/ocultar los detalles del plan
    $('.wcps-toggle-details').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Evitar que el click se propague
        
        var $details = $(this).closest('.wcps-plan').find('.wcps-plan-details');
        $details.slideToggle();
        $(this).text($(this).text() === '+' ? '-' : '+');
    });
    
    // CRÍTICO: Asegurar que el valor se mantenga antes de enviar
    $('form.cart').on('submit', function(e) {
        var $form = $(this);
        var $plansContainer = $form.find('.wcps-plans-container');
        
        console.log('Formulario enviándose...');
        
        if ($plansContainer.length > 0) {
            // Buscar el plan seleccionado
            var $selectedPlan = $form.find('input[name="wcps_selected_plan"]:checked');
            console.log('Plan seleccionado:', $selectedPlan.val());
            
            if ($selectedPlan.length === 0) {
                e.preventDefault();
                alert('Por favor, elige una opción de pago para continuar.');
                return false;
            }
            
            // IMPORTANTE: Crear un campo hidden con el valor seleccionado
            // para asegurar que se envíe con el formulario
            if ($form.find('input[name="wcps_selected_plan_hidden"]').length === 0) {
                $form.append('<input type="hidden" name="wcps_selected_plan" value="' + $selectedPlan.val() + '">');
            } else {
                $form.find('input[name="wcps_selected_plan_hidden"]').val($selectedPlan.val());
            }
            
            console.log('Plan enviado:', $selectedPlan.val());
        }
    });
    
    // Destacar plan seleccionado y asegurar que el valor se registre
    $('input[name="wcps_selected_plan"]').on('change', function() {
        console.log('Radio cambiado a:', $(this).val());
        
        // Remover clase de seleccionado de todos los planes
        $('.wcps-plan').removeClass('wcps-plan-selected');
        
        // Añadir clase al plan seleccionado
        $(this).closest('.wcps-plan').addClass('wcps-plan-selected');
        
        // Asegurar que el radio esté realmente marcado
        $(this).prop('checked', true);
        
        // Habilitar el botón de añadir al carrito
        $('.single_add_to_cart_button').prop('disabled', false);
        
        // Log para debugging
        console.log('Radio checked:', $(this).is(':checked'));
        console.log('Valor actual:', $(this).val());
    });
    
    // Hacer clic en el header del plan para seleccionarlo
    $('.wcps-plan-header').on('click', function(e) {
        // No hacer nada si se hizo clic en el toggle
        if ($(e.target).hasClass('wcps-toggle-details')) {
            return;
        }
        
        // Encontrar y marcar el radio button
        var $radio = $(this).find('input[type="radio"]');
        if ($radio.length > 0 && !$radio.is(':checked')) {
            $radio.prop('checked', true).trigger('change');
        }
    });
    
    // NUEVO: Interceptar el botón de añadir al carrito de AJAX
    $(document).on('click', '.single_add_to_cart_button', function(e) {
        var $button = $(this);
        var $form = $button.closest('form.cart');
        var $plansContainer = $form.find('.wcps-plans-container');
        
        if ($plansContainer.length > 0) {
            var $selectedPlan = $form.find('input[name="wcps_selected_plan"]:checked');
            
            if ($selectedPlan.length === 0) {
                e.preventDefault();
                e.stopImmediatePropagation();
                alert('Por favor, elige una opción de pago para continuar.');
                return false;
            }
        }
    });
    
    // Inicialización: Deshabilitar botón si hay planes pero ninguno seleccionado
    if ($('.wcps-plans-container').length > 0) {
        var $selectedInitial = $('input[name="wcps_selected_plan"]:checked');
        if ($selectedInitial.length === 0) {
            // Opcionalmente, puedes dejar el botón habilitado pero mostrar error al hacer clic
            // $('.single_add_to_cart_button').prop('disabled', true);
            console.log('No hay plan seleccionado inicialmente');
        }
    }
    
    // Debug: Verificar que los valores se están capturando
    $(document).on('submit', 'form.cart', function() {
        console.log('=== Debug Form Submit ===');
        console.log('Datos del formulario:', $(this).serialize());
        console.log('Plan seleccionado:', $('input[name="wcps_selected_plan"]:checked').val());
        console.log('========================');
    });
});
