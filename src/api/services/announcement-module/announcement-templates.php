<?php
/**
 * Announcement Templates - Sistema de plantillas para anuncios
 * Templates predefinidos para diferentes tipos de anuncios comerciales
 */

class AnnouncementTemplates {
    
    // Templates organizados por categoría
    const TEMPLATES = [
        // ===== CATEGORÍAS PARA MALL (PRIORIDAD) =====
        
        'celebracion' => [
            'evento_general' => [
                'name' => 'Evento Especial',
                'template' => '¡No se lo pierdan! {evento} este {dia} a las {hora} en {ubicacion} del Mol. {detalles}',
                'variables' => ['evento', 'dia', 'hora', 'ubicacion', 'detalles', ],
                'example' => [
                    'evento' => 'Gran show de magia familiar',
                    'dia' => 'sábado 20',
                    'hora' => '4',
                    'ubicacion' => 'la plaza central',
                    'detalles' => 'Recuerden. Gran show de magia familiar el sabado 20 a las 4 de la tarde.',                
                ],
                'speech_preset' => 'informativo'
            ],
            'dia_madre' => [
                'name' => 'Día de la Madre',
                'template' => '¡Feliz día de la Madre! El Mol, Barrio Independencia celebra con todas las mamás en su día especial. {mensaje_carino}. {actividad_especial}. {regalo_promocion}.',
                'variables' => ['mensaje_carino', 'actividad_especial', 'regalo_promocion'],
                'example' => [
                    'mensaje_carino' => 'Gracias por su amor incondicional',
                    'actividad_especial' => 'Las invitamos a participar del sorteo de un spa day completo',
                    'regalo_promocion' => 'Retiren una rosa de regalo en informaciones'
                ],
                'speech_preset' => 'oferta'
            ],
            'dia_padre' => [
                'name' => 'Día del Padre',
                'template' => '¡Feliz día del Padre! En el Mol, Barrio Independencia queremos homenajear a todos los papás. {mensaje_especial}. {actividad_del_dia}. {beneficio_especial}.',
                'variables' => ['mensaje_especial', 'actividad_del_dia', 'beneficio_especial'],
                'example' => [
                    'mensaje_especial' => 'Gracias por su esfuerzo y cariño diario',
                    'actividad_del_dia' => 'Los invitamos al torneo de PlayStation en el nivel 2',
                    'beneficio_especial' => '2x1 en cines para papás'
                ],
                'speech_preset' => 'oferta'
            ],
            'dia_nino' => [
                'name' => 'Día del Niño',
                'template' => '¡Feliz día del Niño! El Mol, Barrio Independencia se llena de magia para todos los pequeños. {actividades_dia}. {horario_actividades}. {sorpresas_adicionales}.',
                'variables' => ['actividades_dia', 'horario_actividades', 'sorpresas_adicionales'],
                'example' => [
                    'actividades_dia' => 'Juegos inflables gigantes, pintacaritas y show de payasos',
                    'horario_actividades' => 'Desde las 11 de la mañana hasta las 7 de la tarde',
                    'sorpresas_adicionales' => 'Algodón de azúcar gratis para los primeros 100 niños'
                ],
                'speech_preset' => 'oferta'
            ],
            'celebracion_santos' => [
                'name' => 'Celebración de Santos',
                'template' => '¡Felicidades a {todos_todas} {los_las} {nombre_celebrado} en su día! El Mol, Barrio Independencia les envía un especial saludo en la celebración de {santo_o_fecha}. {mensaje_bendicion}. {beneficio_del_dia}.',
                'variables' => ['todos_todas', 'los_las', 'nombre_celebrado', 'santo_o_fecha', 'mensaje_bendicion', 'beneficio_del_dia'],
                'example' => [
                    'todos_todas' => 'todas',
                    'los_las' => 'las',
                    'nombre_celebrado' => 'Cármenes',
                    'santo_o_fecha' => 'la Virgen del Carmen',
                    'mensaje_bendicion' => '',
                    'beneficio_del_dia' => ''
                ],
                'speech_preset' => 'informativo'
            ]
        ],
        
        'cine' => [
            'estreno_pelicula' => [
                'name' => 'Estreno de Película',
                'template' => '¡Gran estreno en Cines del Mol! {titulo_pelicula} llega {cuando} a nuestras salas. {descripcion_breve}. {horarios_funciones}. {promocion_estreno}.',
                'variables' => ['titulo_pelicula', 'cuando', 'descripcion_breve', 'horarios_funciones', 'promocion_estreno'],
                'example' => [
                    'titulo_pelicula' => 'Avatar 3',
                    'cuando' => 'este jueves a las 2 en punto',
                    'descripcion_breve' => 'La aventura más esperada del año',
                    'horarios_funciones' => 'Funciones cada hora desde las 2 de la tarde. No se lo pierdan !!',
                   
                ],
                'speech_preset' => 'oferta'
            ],
            'promocion_cine' => [
                'name' => 'Promoción de Cine',
                'template' => '¡{dia_promocion} de cine en el Mol, Barrio Independencia! {descripcion_promo}. {peliculas_incluidas}. {condiciones}. {llamado_accion}.',
                'variables' => ['dia_promocion', 'descripcion_promo', 'peliculas_incluidas', 'condiciones', 'llamado_accion'],
                'example' => [
                    'dia_promocion' => 'Miércoles',
                    'descripcion_promo' => 'Todas las entradas a mitad de precio',
                    'peliculas_incluidas' => 'Válido para toda la cartelera',
                    'condiciones' => 'Presentando tu tarjeta del mol',
                    'llamado_accion' => '¡No dejes pasar esta oportunidad!'
                ],
                'speech_preset' => 'oferta'
            ]
        ],
        
        'eventos_infantiles' => [
            'show_infantil' => [
                'name' => 'Show Infantil',
                'template' => '¡Atención niños y niñas! El Mol, Barrio Independencia presenta {show_titulo} {cuando}. {descripcion_show}. {edades_recomendadas}. {ubicacion_detalles}. ¡{mensaje_entusiasta}!',
                'variables' => ['show_titulo', 'cuando', 'descripcion_show', 'edades_recomendadas', 'ubicacion_detalles', 'mensaje_entusiasta'],
                'example' => [
                    'show_titulo' => 'el Show de Plim Plim',
                    'cuando' => 'este sábado a las 11 de la mañana ',
                    'descripcion_show' => 'Con canciones, bailes y mucha diversión',
                    'edades_recomendadas' => 'Especial para niños de dos a ocho años. ',
                    'ubicacion_detalles' => 'Nos vemos en la plaza de eventos nivel 1',
                    'mensaje_entusiasta' => ''
                ],
                'speech_preset' => 'oferta'
            ],
            'taller_infantil' => [
                'name' => 'Taller Infantil',
                'template' => '¡Inscripciones abiertas! Taller de {tipo_taller} para niños en el Mol, Barrio Independencia. {descripcion_taller}. {cuando_donde}. {materiales_incluidos}. {inscripcion_info}.',
                'variables' => ['tipo_taller', 'descripcion_taller', 'cuando_donde', 'materiales_incluidos', 'inscripcion_info'],
                'example' => [
                    'tipo_taller' => 'manualidades creativas',
                    'descripcion_taller' => 'Los niños aprenderán a crear sus propios juguetes',
                    'cuando_donde' => 'Todos los sábados de 11 a 12 horas en sala de eventos',
                    'materiales_incluidos' => 'Todos los materiales incluidos',
                    'inscripcion_info' => 'Cupos limitados en informaciones'
                ],
                'speech_preset' => 'informativo'
            ]
        ],
        
        'recordatorio' => [
            'recordatorio_estacionamiento' => [
                'name' => 'Recordatorio de Estacionamiento',
                'template' => 'Estimados visitantes del Mol, Barrio Independencia: {recordatorio_principal}. {informacion_adicional}. {instruccion_final}.',
                'variables' => ['recordatorio_principal', 'informacion_adicional', 'instruccion_final'],
                'example' => [
                    'recordatorio_principal' => 'recuerden que las primeras 2 horas de estacionamiento son gratuitas',
                    'informacion_adicional' => 'Tarifas preferenciales con compras superiores a $20.000',
                    'instruccion_final' => 'Por favor, validen su ticket en las cajas antes de retirarse'
                ],
                'speech_preset' => 'informativo'
            ],
            'recordatorio_seguridad' => [
                'name' => 'Recordatorio de Seguridad',
                'template' => 'El Mol, Barrio Independencia les recuerda: {mensaje_seguridad}. {recomendacion_especifica}. {donde_ayuda}. Su seguridad es nuestra prioridad.',
                'variables' => ['mensaje_seguridad', 'recomendacion_especifica', 'donde_ayuda'],
                'example' => [
                    'mensaje_seguridad' => 'mantengan a los niños siempre bajo supervisión',
                    'recomendacion_especifica' => 'Establezcan un punto de encuentro con su familia',
                    'donde_ayuda' => 'Guardias disponibles en todos los niveles'
                ],
                'speech_preset' => 'informativo'
            ]
        ],
        
        // ===== CATEGORÍAS PARA SUPERMERCADOS (ORIGINALES) =====
        
        'ofertas' => [
            'descuento_simple' => [
                'name' => 'Descuento Simple',
                'template' => 'Atención clientes, {producto} con un {descuento}% de descuento. {duracion}.',
                'variables' => ['producto', 'descuento', 'duracion'],
                'example' => ['producto' => 'todas las frutas', 'descuento' => '30', 'duracion' => 'Solo por hoy'],
                'speech_preset' => 'oferta'
            ],
            'descuento_precio' => [
                'name' => 'Descuento con Precios',
                'template' => 'Oferta especial en {producto}. Antes ${precio_antes}, ahora solo ${precio_ahora}. {duracion}.',
                'variables' => ['producto', 'precio_antes', 'precio_ahora', 'duracion'],
                'example' => ['producto' => 'carnes rojas', 'precio_antes' => '9.990', 'precio_ahora' => '6.990', 'duracion' => 'Válido hasta el domingo'],
                'speech_preset' => 'oferta'
            ],
            'dos_por_uno' => [
                'name' => '2x1 o 3x2',
                'template' => 'Increíble promoción {tipo} en {producto}. {detalle}. {duracion}.',
                'variables' => ['tipo', 'producto', 'detalle', 'duracion'],
                'example' => ['tipo' => '2x1', 'producto' => 'bebidas y jugos', 'detalle' => 'Lleva 2 y paga solo 1', 'duracion' => 'Solo este fin de semana'],
                'speech_preset' => 'oferta'
            ],
            'liquidacion' => [
                'name' => 'Liquidación',
                'template' => 'Última liquidación de {temporada}. {producto} con hasta {descuento}% de descuento. {mensaje_urgencia}.',
                'variables' => ['temporada', 'producto', 'descuento', 'mensaje_urgencia'],
                'example' => ['temporada' => 'verano', 'producto' => 'Toda la ropa', 'descuento' => '70', 'mensaje_urgencia' => 'Últimas unidades disponibles'],
                'speech_preset' => 'oferta'
            ]
        ],
        
        'horarios' => [
            'apertura' => [
                'name' => 'Apertura',
                'template' => 'Buenos {momento}, les informamos que {negocio} ya está abierto. Los esperamos hasta las {hora_cierre}. {mensaje_extra}',
                'variables' => ['momento', 'negocio', 'hora_cierre', 'mensaje_extra'],
                'example' => ['momento' => 'días', 'negocio' => 'nuestro supermercado', 'hora_cierre' => '10 de la noche', 'mensaje_extra' => 'Gracias por su preferencia'],
                'speech_preset' => 'informativo'
            ],
            'proximo_cierre' => [
                'name' => 'Próximo Cierre',
                'template' => 'Estimados clientes, les informamos que cerraremos en {minutos} minutos. Por favor, acérquense a las cajas. {mensaje_extra}',
                'variables' => ['minutos', 'mensaje_extra'],
                'example' => ['minutos' => '30', 'mensaje_extra' => 'Mañana los esperamos desde las 8 de la mañana'],
                'speech_preset' => 'cierre'
            ],
            'cierre_departamento' => [
                'name' => 'Cierre de Departamento',
                'template' => 'Atención clientes, el departamento de {departamento} cerrará en {minutos} minutos. {instruccion}.',
                'variables' => ['departamento', 'minutos', 'instruccion'],
                'example' => ['departamento' => 'carnicería', 'minutos' => '15', 'instruccion' => 'Si necesita atención, acérquese ahora'],
                'speech_preset' => 'informativo'
            ]
        ],
        
        'emergencias' => [
            'evacuacion' => [
                'name' => 'Evacuación',
                'template' => 'Atención, atención. {motivo}. Por favor, evacuar el edificio de manera ordenada por las salidas de emergencia. {instruccion_extra}.',
                'variables' => ['motivo', 'instruccion_extra'],
                'example' => ['motivo' => 'Se ha activado la alarma de emergencia', 'instruccion_extra' => 'Y porfavor no utilice los ascensores.Repito, no utilice los ascensores.'],
                'speech_preset' => 'emergencia'
            ],
            'emergencia_medica' => [
                'name' => 'Emergencia Médica',
                'template' => 'Atención, se solicita {profesional} en {ubicacion} de manera urgente. {repetir}',
                'variables' => ['profesional', 'ubicacion', 'repetir'],
                'example' => ['profesional' => 'personal médico o paramédico', 'ubicacion' => 'la sección de electrodomésticos', 'repetir' => 'Repito, personal médico en electrodomésticos'],
                'speech_preset' => 'emergencia'
            ]
        ],
        
        'servicios' => [
            'llamado_caja' => [
                'name' => 'Apertura de Caja',
                'template' => 'Atención clientes, se ha habilitado la {caja}. Pueden acercarse para ser atendidos.',
                'variables' => ['caja'],
                'example' => ['caja' => 'caja número 5'],
                'speech_preset' => 'informativo'
            ],
            'vehiculo_mal_estacionado' => [
                'name' => 'Vehículo Mal Estacionado',
                'template' => 'Atención, se solicita al propietario del vehículo {descripcion}, patente {patente}, retirarlo de {ubicacion}. {consecuencia}.',
                'variables' => ['descripcion', 'patente', 'ubicacion', 'consecuencia'],
                'example' => ['descripcion' => 'sedan gris', 'patente' => 'AB CD 12', 'ubicacion' => 'la entrada de emergencia', 'consecuencia' => 'De lo contrario será remolcado'],
                'speech_preset' => 'informativo'
            ],
            'niño_perdido' => [
                'name' => 'Niño Perdido',
                'template' => 'Atención clientes, {descripcion_niño} se encuentra en {ubicacion}. Sus padres pueden acercarse a retirarlo. {mensaje_calma}.',
                'variables' => ['descripcion_niño', 'ubicacion', 'mensaje_calma'],
                'example' => ['descripcion_niño' => 'el pequeño Matías de polera roja', 'ubicacion' => 'atención al cliente', 'mensaje_calma' => 'El niño está bien y acompañado'],
                'speech_preset' => 'informativo'
            ]
        ],
        
        'eventos' => [
            'evento_especial' => [
                'name' => 'Evento Especial',
                'template' => '{saludo}, los invitamos a {evento} que se realizará {cuando} en {ubicacion}. {detalles}.',
                'variables' => ['saludo', 'evento', 'cuando', 'ubicacion', 'detalles'],
                'example' => ['saludo' => 'Estimados clientes', 'evento' => 'nuestra degustación de vinos', 'cuando' => 'en 30 minutos', 'ubicacion' => 'el pasillo central', 'detalles' => 'Tendremos productos con descuentos especiales'],
                'speech_preset' => 'informativo'
            ]
        ]
    ];
    
    /**
     * Obtiene todos los templates disponibles
     */
    public static function getAllTemplates() {
        return self::TEMPLATES;
    }
    
    /**
     * Obtiene templates por categoría
     */
    public static function getTemplatesByCategory($category) {
        return self::TEMPLATES[$category] ?? [];
    }
    
    /**
     * Obtiene un template específico
     */
    public static function getTemplate($category, $templateId) {
        return self::TEMPLATES[$category][$templateId] ?? null;
    }
    
    /**
     * Genera anuncio desde template
     */
    public static function generateFromTemplate($category, $templateId, $variables = []) {
        $template = self::getTemplate($category, $templateId);
        
        if (!$template) {
            throw new Exception("Template no encontrado: $category/$templateId");
        }
        
        $text = $template['template'];
        
        // Reemplazar variables
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        // Verificar que no queden variables sin reemplazar
        if (preg_match('/\{[^}]+\}/', $text)) {
            throw new Exception("Faltan variables por completar en el template");
        }
        
        return [
            'text' => $text,
            'speech_preset' => $template['speech_preset'],
            'template_name' => $template['name']
        ];
    }
    
    /**
     * Valida que todas las variables requeridas estén presentes
     */
    public static function validateVariables($category, $templateId, $variables) {
        $template = self::getTemplate($category, $templateId);
        
        if (!$template) {
            return ['valid' => false, 'error' => 'Template no encontrado'];
        }
        
        $missing = [];
        foreach ($template['variables'] as $required) {
            if (!isset($variables[$required]) || empty(trim($variables[$required]))) {
                $missing[] = $required;
            }
        }
        
        if (!empty($missing)) {
            return [
                'valid' => false,
                'error' => 'Faltan variables requeridas',
                'missing' => $missing
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Obtiene lista simple de templates para UI
     */
    public static function getTemplateList() {
        $list = [];
        
        foreach (self::TEMPLATES as $category => $templates) {
            foreach ($templates as $id => $template) {
                $list[] = [
                    'category' => $category,
                    'id' => $id,
                    'name' => $template['name'],
                    'variables' => $template['variables'],
                    'example' => $template['example']
                ];
            }
        }
        
        return $list;
    }
}
?>