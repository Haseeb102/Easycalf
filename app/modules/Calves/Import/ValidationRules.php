<?php
/**
 * Validation Rules
 * Validates imported calf data
 */
class ValidationRules {
    /**
     * Validate a single calf row
     */
    public function validateRow($rowData, $rowNumber) {
        $errors = array();
        
        // Validate Calf ID
        if (empty($rowData['calf_id'])) {
            $errors[] = "Row $rowNumber: Calf ID is required";
        }
        
        // Validate Birth Date
        if (empty($rowData['birth_date'])) {
            $errors[] = "Row $rowNumber: Birth date is required";
        } else {
            $parsedDate = $this->parseDate($rowData['birth_date']);
            if (!$parsedDate) {
                $errors[] = "Row $rowNumber: Invalid birth date format: {$rowData['birth_date']}";
            } else {
                $rowData['birth_date'] = $parsedDate;
            }
        }
        
        // Validate Sex
        if (empty($rowData['sex'])) {
            $errors[] = "Row $rowNumber: Sex is required";
        } else {
            $sex = $this->parseSex($rowData['sex']);
            if (!$sex) {
                $errors[] = "Row $rowNumber: Invalid sex value: {$rowData['sex']}. Must be male/female or M/F";
            } else {
                $rowData['sex'] = $sex;
            }
        }
        
        // Validate Birth Weight (if provided)
        if (!empty($rowData['birth_weight'])) {
            if (!is_numeric($rowData['birth_weight']) || $rowData['birth_weight'] < 0) {
                $errors[] = "Row $rowNumber: Invalid birth weight: {$rowData['birth_weight']}";
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $rowData
        );
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateString) {
        $dateString = trim($dateString);
        
        // Try common formats
        $formats = array(
            'd-m-Y',
            'd/m/Y',
            'Y-m-d',
            'm/d/Y',
            'd.m.Y',
            'Y/m/d',
        );
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return $date->format('Y-m-d');
            }
        }
        
        // Try strtotime as last resort
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return false;
    }

    /**
     * Parse sex from various formats
     */
    private function parseSex($sexString) {
        $sexString = strtolower(trim($sexString));
        
        $maleForms = array('m', 'male', 'bull', 'boy');
        $femaleForms = array('f', 'female', 'heifer', 'girl', 'cow');
        
        if (in_array($sexString, $maleForms)) {
            return 'male';
        }
        
        if (in_array($sexString, $femaleForms)) {
            return 'female';
        }
        
        return false;
    }

    /**
     * Parse health status from various formats
     */
    public function parseHealthStatus($healthString) {
        if (empty($healthString)) {
            return 'healthy';
        }
        
        $healthString = strtolower(trim($healthString));
        
        $healthyForms = array('healthy', 'good', 'ok', 'normal', 'fine');
        $attentionForms = array('needs attention', 'attention', 'watch', 'monitor', 'caution', 'needs_attention');
        $sickForms = array('sick', 'ill', 'poor', 'bad', 'treatment');
        
        if (in_array($healthString, $healthyForms)) {
            return 'healthy';
        }
        
        if (in_array($healthString, $attentionForms)) {
            return 'needs_attention';
        }
        
        if (in_array($healthString, $sickForms)) {
            return 'sick';
        }
        
        return 'healthy';
    }
}
?>