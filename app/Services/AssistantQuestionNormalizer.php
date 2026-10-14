<?php

namespace App\Services;

class AssistantQuestionNormalizer
{
    /** Conservative phrase aliases also work when the model is disabled/offline. */
    public function normalize(string $question): string
    {
        $aliases = [
            'pila kabuok|pila kabook|pila kabouk|pila tanan|pila man' => 'pila',
            'mga tawo|katawhan|mga residente|residents|people' => 'population',
            'hamtong' => 'adults',
            'kababaihan|women' => 'female',
            'kalalakin-an|men' => 'male',
            'senior citizens|tiguwang' => 'seniors',
            'mga bata|kids' => 'children',
            'panimalays' => 'households',
            'niaging bulana|miaging bulana|miaging buwan|niaging buwan|last mo' => 'last month',
            'karong tuiga|karong tuig' => 'this year',
            'niaging tuig|miaging tuiga' => 'last year',
            'sunod tuig|sunod nga tuig|sunod bulan|sunod nga bulan|next month' => 'forecast',
        ];
        foreach ($aliases as $pattern => $replacement) {
            $question = preg_replace('/(?<![\pL\pN])(?:'.$pattern.')(?![\pL\pN])/iu', $replacement, $question);
        }

        return trim(preg_replace('/\s+/u', ' ', $question));
    }
}
