$(document).ready(function() {
    // Implementazione della funzionalità di ricerca per i metodi, si la pagina metodi non permette di fare altro se no impazzivo
    $("input[placeholder='Cerca metodi...']").on('keyup', function() {
        // Ottieni il valore di ricerca e convertilo in minuscolo
        let searchTerm = $(this).val().toLowerCase();
        
        // Itera su tutte le method-card e mostra/nascondi in base alla ricerca
        $(".method-card").each(function() {
            let methodName = $(this).find("h4").text().toLowerCase();
            let methodDescription = $(this).find("p").text().toLowerCase();
            
            // Controlla se il testo del metodo contiene il termine di ricerca
            if (methodName.includes(searchTerm) || methodDescription.includes(searchTerm)) {
                $(this).parent().show();
            } else {
                $(this).parent().hide();
            }
        });
        
        // Mostra un messaggio se non ci sono risultati
        let visibleItems = $(".method-card").parent(":visible").length;
        if (visibleItems === 0 && searchTerm !== '') {
            // Se non c'è già un messaggio, aggiungilo
            if ($(".no-results").length === 0) {
                $(".row").append('<div class="col-12 text-center no-results"><p>Nessun metodo trovato per "' + searchTerm + '"</p></div>');
            } else {
                // Aggiorna il messaggio esistente
                $(".no-results p").text('Nessun metodo trovato per "' + searchTerm + '"');
            }
        } else {
            // Rimuovi il messaggio se ci sono risultati o la ricerca è vuota
            $(".no-results").remove();
        }
    });
}); 