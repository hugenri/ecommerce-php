<?php

namespace App\Core\Validation;

class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {

            $value = $data[$field] ?? null;
            $rulesList = explode('|', $ruleString);

            if (
                in_array('nullable', $rulesList) &&
                ($value === null || $value === '')
            ) {
                continue;
            }

            foreach ($rulesList as $rule) {

                if (str_contains($rule, ':')) {
                    [$ruleName, $param] = explode(':', $rule, 2);
                } else {
                    $ruleName = $rule;
                    $param = null;
                }

                $method = 'validate_' . $ruleName;

                if (!method_exists($this, $method)) {
                    continue;
                }

                $error = $this->$method(
                    $field,
                    $value ?? '',
                    $param
                );

                if ($error) {

                    $errors[$field][] = $error;

                    if ($ruleName === 'required') {
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    private function validate_required($field, $value)
    {
        if ($value === '') {
            return "El campo {$field} es obligatorio.";
        }

        return null;
    }

    private function validate_string($field, $value)
    {
        if (!is_string($value)) {
            return "El campo {$field} debe ser texto.";
        }

        return null;
    }

    private function validate_min($field, $value, $param)
    {
        if (strlen((string) $value) < (int) $param) {
            return "El campo {$field} debe tener al menos {$param} caracteres.";
        }

        return null;
    }

    private function validate_max($field, $value, $param)
    {
        if (strlen((string) $value) > (int) $param) {
            return "El campo {$field} no debe superar {$param} caracteres.";
        }

        return null;
    }

    private function validate_only_letters($field, $value)
    {
        if (
            $value !== '' &&
            !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$/', $value)
        ) {
            return "El campo {$field} solo permite letras y espacios.";
        }

        return null;
    }

    private function validate_letters_spaces_numbers($field, $value)
    {
        if (
            $value !== '' &&
            !preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 ]+$/', $value)
        ) {
            return "El campo {$field} solo permite letras, números y espacios.";
        }

        return null;
    }

    private function validate_text($field, $value)
    {
        return null;
    }

    private function validate_numeric($field, $value)
    {
        if (
            $value !== '' &&
            !ctype_digit((string) $value)
        ) {
            return "El campo {$field} debe ser numérico.";
        }

        return null;
    }

    private function validate_min_digits($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        if (strlen((string) $value) < (int) $param) {
            return "El campo {$field} debe tener al menos {$param} dígitos.";
        }

        return null;
    }

    private function validate_max_digits($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        if (strlen((string) $value) > (int) $param) {
            return "El campo {$field} no debe superar {$param} dígitos.";
        }

        return null;
    }

    private function validate_digits($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        if (strlen((string) $value) !== (int) $param) {
            return "El campo {$field} debe tener exactamente {$param} dígitos.";
        }

        return null;
    }

    private function validate_decimal($field, $value)
    {
        if (
            $value !== '' &&
            !is_numeric($value)
        ) {
            return "El campo {$field} debe ser un número decimal válido.";
        }

        return null;
    }

    private function validate_max_integer_digits($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        $parts = explode('.', (string) $value);

        if (strlen($parts[0]) > (int) $param) {
            return "El campo {$field} no debe superar {$param} dígitos enteros.";
        }

        return null;
    }

    private function validate_max_decimal_digits($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        $parts = explode('.', (string) $value);

        $decimals = $parts[1] ?? '';

        if (strlen($decimals) > (int) $param) {
            return "El campo {$field} no debe superar {$param} decimales.";
        }

        return null;
    }

    private function validate_email($field, $value)
    {
        if (
            $value !== '' &&
            !filter_var($value, FILTER_VALIDATE_EMAIL)
        ) {
            return "El campo {$field} debe tener un correo electrónico con formato válido.";
        }

        return null;
    }

    private function validate_url($field, $value)
    {
        if ($value === '') {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return "El campo {$field} debe ser una URL válida.";
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (!in_array($scheme, ['http', 'https'], true)) {
            return "El campo {$field} debe iniciar con http:// o https://";
        }

        return null;
    }

    private function validate_date($field, $value)
    {
        if ($value === '') {
            return null;
        }

        $dateObj = \DateTime::createFromFormat('Y-m-d', $value);

        if (
            !$dateObj ||
            $dateObj->format('Y-m-d') !== $value
        ) {
            return "El campo {$field} debe tener formato YYYY-MM-DD.";
        }

        return null;
    }

    private function validate_in($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        $allowed = array_map('trim', explode(',', $param));

        if (!in_array($value, $allowed, true)) {
            return "El campo {$field} debe ser uno de: " . implode(', ', $allowed) . '.';
        }

        return null;
    }

    private function validate_format_password($field, $value, $param)
    {
        if ($value === '') {
            return null;
        }

        $min = 8;
        $max = 16;

        if ($param && preg_match('/^\d+,\d+$/', $param)) {
            [$min, $max] = array_map(
                'intval',
                explode(',', $param)
            );
        }

        $len = strlen($value);

        if ($len < $min || $len > $max) {
            return "El campo {$field} debe tener entre {$min} y {$max} caracteres.";
        }

        if (!preg_match('/[a-z]/', $value)) {
            return "El campo {$field} debe contener al menos una minúscula.";
        }

        if (!preg_match('/[A-Z]/', $value)) {
            return "El campo {$field} debe contener al menos una mayúscula.";
        }

        if (!preg_match('/\d/', $value)) {
            return "El campo {$field} debe contener al menos un dígito.";
        }

        if (!preg_match('/[^\w\s]/', $value)) {
            return "El campo {$field} debe contener al menos un carácter especial.";
        }

        return null;
    }

    public function hasErrors(array $errors): bool
    {
        return !empty($errors);
    }
}
