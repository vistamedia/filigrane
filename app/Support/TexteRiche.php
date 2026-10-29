<?php

namespace App\Support;

/**
 * Le texte saisi dans les champs riches sort d'ici avant d'être réaffiché
 * ailleurs que dans l'éditeur : diaporama, catalogue PDF, tableur.
 *
 * L'application ne compte que deux comptes nominatifs, tous deux de
 * confiance : il ne s'agit pas de se défendre d'un attaquant, mais de ne pas
 * réinjecter tel quel, dans une page ou un document, un balisage dont on ne
 * contrôle pas l'origine.
 */
class TexteRiche
{
    /** Ce que l'éditeur sait produire, et rien d'autre. */
    private const BALISES = '<p><br><strong><b><em><i><u><a><ul><ol><li><h2><h3><blockquote>';

    /**
     * Balisage réduit aux seules balises attendues, liens assainis.
     *
     * Un champ sans texte revient vide, et non « <p></p> » : l'éditeur
     * enregistre ce balisage dès qu'on a posé le curseur dans un champ sans
     * rien y écrire, et un bloc « Note » vide s'afficherait pour rien.
     */
    public static function nettoyer(?string $html): string
    {
        if (self::estVide($html)) {
            return '';
        }

        $html = strip_tags((string) $html, self::BALISES);

        // strip_tags conserve les attributs des balises autorisées : on ne
        // garde donc que les href manifestement inoffensifs.
        $html = preg_replace_callback(
            '/<a\b[^>]*>/i',
            function (array $balise): string {
                preg_match('/href\s*=\s*["\']([^"\']*)["\']/i', $balise[0], $lien);
                $url = $lien[1] ?? '';

                return preg_match('#^(https?://|mailto:)#i', $url)
                    ? '<a href="'.htmlspecialchars($url, ENT_QUOTES).'" rel="noopener noreferrer" target="_blank">'
                    : '<a>';
            },
            $html,
        ) ?? '';

        return trim($html);
    }

    /**
     * Le même texte, mais sans balisage : pour une cellule de tableur.
     * Les fins de paragraphe et les retours à la ligne sont conservés.
     */
    public static function enTexte(?string $html): string
    {
        $texte = preg_replace('#<br\s*/?>#i', "\n", (string) $html) ?? '';
        $texte = preg_replace('#</(p|li|h2|h3|blockquote)>#i', "\n", $texte) ?? '';
        $texte = preg_replace('#<li[^>]*>#i', '• ', $texte) ?? '';
        $texte = html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Au plus une ligne vide d'affilée, et pas d'espaces en bout de ligne.
        $texte = preg_replace("/[ \t]+\n/", "\n", $texte) ?? '';
        $texte = preg_replace("/\n{3,}/", "\n\n", $texte) ?? '';

        return trim($texte);
    }

    /**
     * Vrai lorsque le champ ne contient que du balisage vide — l'éditeur
     * enregistre volontiers « <p></p> » pour un champ auquel on a touché sans
     * rien écrire.
     */
    public static function estVide(?string $html): bool
    {
        return self::enTexte($html) === '';
    }
}
