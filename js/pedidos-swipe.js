// Funciones de swipe y manejo de eliminación de productos

function moverSwipe(event) {
    if (!swipeActive || !currentSwipeElement) return;
    
    // Prevenir scroll durante el swipe
    event.preventDefault();
    
    // Obtener coordenadas actuales del touch
    const touch = event.touches[0];
    const currentX = touch.clientX;
    const currentY = touch.clientY;
    
    // Calcular distancia del movimiento
    const deltaX = currentX - swipeStartX;
    const deltaY = currentY - swipeStartY;
    
    // Solo aplicar transformación si el movimiento es principalmente horizontal
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
        // Limitar el movimiento máximo
        const maxMovement = 150;
        const limitedDeltaX = Math.max(-maxMovement, Math.min(maxMovement, deltaX));
        
        // Aplicar transformación visual
        currentSwipeElement.style.transform = `translateX(${limitedDeltaX}px)`;
        
        // Cambiar opacidad basada en la distancia
        const opacity = Math.max(0.3, 1 - Math.abs(limitedDeltaX) / maxMovement * 0.7);
        currentSwipeElement.style.opacity = opacity;
        
        // Cambiar color de fondo si el swipe es suficiente
        if (Math.abs(limitedDeltaX) > 100) {
            currentSwipeElement.style.backgroundColor = '#ffebee';
        } else {
            currentSwipeElement.style.backgroundColor = '';
        }
    }
}

function finalizarSwipe(productoId, event) {
    if (!swipeActive || !currentSwipeElement) return;
    
    // Obtener coordenadas finales del touch
    const touch = event.changedTouches[0];
    const swipeEndX = touch.clientX;
    const swipeEndY = touch.clientY;
    const swipeEndTime = Date.now();
    
    // Calcular distancia y tiempo del swipe
    const deltaX = swipeEndX - swipeStartX;
    const deltaY = swipeEndY - swipeStartY;
    const deltaTime = swipeEndTime - swipeStartTime;
    
    // Verificar si es un swipe horizontal válido
    const minSwipeDistance = 100; // mínimo 100px
    const maxSwipeTime = 1000; // máximo 1 segundo
    const maxVerticalMovement = 50; // máximo movimiento vertical
    
    const isValidSwipe = Math.abs(deltaX) >= minSwipeDistance && 
                        Math.abs(deltaY) <= maxVerticalMovement && 
                        deltaTime <= maxSwipeTime;
    
    if (isValidSwipe) {
        // Swipe válido - animar hacia fuera y mostrar modal
        currentSwipeElement.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
        currentSwipeElement.style.transform = `translateX(${deltaX > 0 ? '100%' : '-100%'})`;
        currentSwipeElement.style.opacity = '0';
        
        // Mostrar modal después de la animación
        setTimeout(() => {
            const producto = productos.find(p => p.id === productoId);
            if (producto) {
                mostrarModalEliminar(producto);
            }
            resetSwipeAnimation();
        }, 300);
    } else {
        // Swipe no válido - animar de vuelta a la posición original
        currentSwipeElement.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out, background-color 0.3s ease-out';
        currentSwipeElement.style.transform = 'translateX(0)';
        currentSwipeElement.style.opacity = '1';
        currentSwipeElement.style.backgroundColor = '';
        
        // Limpiar después de la animación
        setTimeout(() => {
            resetSwipeAnimation();
        }, 300);
    }
    
    // Resetear variables
    swipeActive = false;
    swipeStartX = 0;
    swipeStartY = 0;
    swipeStartTime = 0;
}

function resetSwipeAnimation() {
    if (currentSwipeElement) {
        currentSwipeElement.classList.remove('swiping');
        currentSwipeElement.style.transition = '';
        currentSwipeElement.style.transform = '';
        currentSwipeElement.style.opacity = '';
        currentSwipeElement.style.backgroundColor = '';
        currentSwipeElement = null;
    }
}

function mostrarModalEliminar(producto) {
    // Usar la función esProductoFardo que considera el campo TV como fuente de verdad
    const esModoFardos = esProductoFardo(producto);
    
    // Configurar modal con descripción + contenido apropiado según el modo
    let textoProducto = producto.descripcion;
    
    if (esModoFardos) {
        // Modo fardos: mostrar contenido1 si existe
        if (producto.contenido1) {
            textoProducto += ` - ${producto.contenido1}`;
        }
    } else {
        // Modo unidades: mostrar contenido2 si existe
        if (producto.contenido2) {
            textoProducto += ` - ${producto.contenido2}`;
        }
    }
    
    document.getElementById('productoEliminarNombre').textContent = textoProducto;
    document.getElementById('eliminarProductoModal').style.display = 'flex';
}

function cerrarModalEliminar() {
    document.getElementById('eliminarProductoModal').style.display = 'none';
    productoAEliminar = null;
}

function confirmarEliminarProducto() {
    if (productoAEliminar) {
        eliminarProducto(productoAEliminar.id);
        cerrarModalEliminar();
    }
}