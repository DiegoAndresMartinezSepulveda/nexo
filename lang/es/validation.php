<?php
return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Escribe un correo electrónico válido.',
    'unique' => 'El valor de :attribute ya está registrado.',
    'in' => 'El valor seleccionado para :attribute no es válido.',
    'array' => 'El campo :attribute debe ser una lista.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'file' => 'El campo :attribute debe ser un archivo válido.',
    'uploaded' => 'No se pudo subir :attribute. Revisa el tamaño del archivo.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'max' => ['string' => ':attribute no debe superar :max caracteres.', 'array' => 'Puedes enviar hasta :max elementos en :attribute.', 'file' => 'El archivo :attribute no debe superar :max KB.', 'numeric' => ':attribute no debe ser mayor que :max.'],
    'min' => ['string' => ':attribute debe tener al menos :min caracteres.', 'numeric' => ':attribute debe ser al menos :min.'],
    'attributes' => ['title' => 'título', 'description' => 'descripción', 'status' => 'estado', 'environment' => 'ambiente', 'priority' => 'prioridad', 'due_date' => 'fecha límite', 'checklist_text' => 'pasos de entrega', 'email' => 'correo', 'password' => 'contraseña', 'name' => 'nombre', 'files' => 'archivos'],
];
