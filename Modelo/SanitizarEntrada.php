<?php
/**
 * Modelo/SanitizarEntrada.php
 * Limpia los datos que llegan por $_POST antes de validarlos.
 */
class SanitizarEntrada
{
    // Quita etiquetas HTML, espacios extra y caracteres de control
    public static function texto($valor): string
    {
        $valor = strip_tags((string)$valor);
        $valor = preg_replace('/[\x00-\x1F\x7F]/u', '', $valor) ?? '';
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? '';
        return trim($valor);
    }
}
