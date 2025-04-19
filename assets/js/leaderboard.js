console.log("✅ leaderboard.js caricato  lcorrettamente!");
$(document).ready(function() {
    // Gestione dei filtri della classifica
    $(".filter-buttons button").on('click', function() {
        $(".filter-buttons button").removeClass('active');
        $(this).addClass('active');
        let filterType = $(this).text();

        // Gestisci la visualizzazione del selettore metodi
        if (filterType === "Per Metodo") {
            // Carica i metodi disponibili e mostra il selettore
            loadAvailableMethods();
        } else {
            // Nascondi il selettore metodi per gli altri filtri
            $(".method-filter").hide();
            
            // Chiamata AJAX per aggiornare la classifica in base al filtro selezionato
            loadLeaderboard(filterType);
        }
    });
    
    // Funzione per caricare i metodi disponibili
    function loadAvailableMethods() {
        $.ajax({
            url: '../api/get_leaderboard.php',
            type: 'POST',
            data: {
                filter: 'get_all_methods'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.methods.length > 0) {
                    // Svuota e riempi il selettore con i metodi
                    let selectElem = $("#method-select");
                    selectElem.empty();
                    selectElem.append('<option value="">Seleziona un metodo</option>');
                    
                    // Aggiungi i metodi al selettore
                    $.each(response.methods, function(i, method) {
                        selectElem.append('<option value="' + method.id + '">' + method.nome + ' (' + method.utenti_count + ' giocatori)</option>');
                    });
                    
                    // Mostra il selettore metodi
                    $(".method-filter").show();
                    
                    // Carica i dati generali per metodo come default
                    loadLeaderboard("Per Metodo");
                } else {
                    // Se non ci sono metodi, mostra un messaggip
                    $(".method-filter").hide();
                    alert("Nessun metodo disponibile con dati.");
                }
            },
            error: function() {
                alert("Errore durante il caricamento dei metodi.");
            }
        });
    }
    
  
    $("#method-select").on('change', function() {
        let methodId = $(this).val();
        
        if (methodId) {
            // Carica la classifica per il metodo specifico
            $.ajax({
                url: '../api/get_leaderboard.php',
                type: 'POST',
                data: {
                    filter: 'method_specific',
                    method_id: methodId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateLeaderboardTable(response.data, response.user_rank);
                        
                        // Aggiorna le intestazioni della tabella
                        $(".leaderboard-table thead tr").html(`
                            <th class="rank">Pos.</th>
                            <th>Utente</th>
                            <th>Metodo</th>
                            <th>Shinies</th>
                            <th>Pokémon Unici</th>
                            <th>Media Incontri</th>
                        `);
                    } else {
                        alert('Errore nel caricamento della classifica (unlucky): ' + response.message);
                    }
                },
                error: function() {
                    alert('Errore di connessione. Riprova mai mai mai più.');
                }
            });
        } else {
            // Se non è stato selezionato un metodo specifico, carica la vista generale dei metodi
            loadLeaderboard("Per Metodo");
        }
    });
    
    // Funzione per caricare la classifica in base al filtro
    function loadLeaderboard(filterType) {
        $.ajax({
            url: '../api/get_leaderboard.php',
            type: 'POST',
            data: {
                filter: filterType
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Aggiorna la tabella della classifica
                    updateLeaderboardTable(response.data, response.user_rank);
                } else {
                    // Mostra un messaggio di errore
                    alert('Errore nel caricamento della classifica: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Mostra un messaggio di errore
                alert('Errore di connessione. Riprova tardi, molto tardi.');
            }
        });
    }
    
    // Funzione per aggiornare la tabella della classifica
    function updateLeaderboardTable(leaderboardData, userRank) {
        // Aggiorna il rank dell'utente nella card superiore
        $(".rank-number").text("#" + userRank);
        
        // Svuota la tabella esistente
        $(".leaderboard-table tbody").empty();
        
        // Popola la tabella con i nuovi dati
        let rank = 0;
        let userId = $(".user-row").data("user-id");
        
        leaderboardData.forEach(function(row) {
            rank++;
            let isCurrentUser = (row.idUtente == userId);
            let rankClass = "rank-" + rank;
            
            let rankDisplay = (rank == 1) ? "<i class='fas fa-crown'></i>" : "#" + rank;
            let mediaIncontri = (row.media_incontri > 0) ? Math.round(row.media_incontri) : '-';
            let primaCattura = row.prima_cattura ? formatDate(row.prima_cattura) : '-';
            
    
            let rowHtml;
            if (row.hasOwnProperty('metodo')) {
                rowHtml = `
                    <tr class="${isCurrentUser ? 'user-row' : ''}">
                        <td class="rank ${(rank <= 3) ? 'top-3 ' + rankClass : ''}">${rankDisplay}</td>
                        <td>${row.username}</td>
                        <td>${row.metodo || '-'}</td>
                        <td>${row.shinies_per_metodo || 0}</td>
                        <td>${row.pokemon_diversi_per_metodo || 0}</td>
                        <td>${row.media_incontri > 0 ? Math.round(row.media_incontri) : '-'}</td>
                    </tr>
                `;
                
                if (rank === 1) {
                    $(".leaderboard-table thead tr").html(`
                        <th class="rank">Pos.</th>
                        <th>Utente</th>
                        <th>Metodo</th>
                        <th>Shinies</th>
                        <th>Pokémon Unici</th>
                        <th>Media Incontri</th>
                    `);
                }
            } else if (row.hasOwnProperty('shinies_per_metodo')) {
                rowHtml = `
                    <tr class="${isCurrentUser ? 'user-row' : ''}">
                        <td class="rank ${(rank <= 3) ? 'top-3 ' + rankClass : ''}">${rankDisplay}</td>
                        <td>${row.username}</td>
                        <td>${$("#method-select option:selected").text().split('(')[0].trim()}</td>
                        <td>${row.shinies_per_metodo || 0}</td>
                        <td>${row.pokemon_diversi_per_metodo || 0}</td>
                        <td>${row.media_incontri > 0 ? Math.round(row.media_incontri) : '-'}</td>
                    </tr>
                `;
                
                if (rank === 1) {
                    $(".leaderboard-table thead tr").html(`
                        <th class="rank">Pos.</th>
                        <th>Utente</th>
                        <th>Metodo</th>
                        <th>Shinies</th>
                        <th>Pokémon Unici</th>
                        <th>Media Incontri</th>
                    `);
                }
            } else {
                // Per i filtri standard
                rowHtml = `
                    <tr class="${isCurrentUser ? 'user-row' : ''}">
                        <td class="rank ${(rank <= 3) ? 'top-3 ' + rankClass : ''}">${rankDisplay}</td>
                        <td>${row.username}</td>
                        <td>${row.shinies_catturati}</td>
                        <td>${row.pokemon_diversi}</td>
                        <td>${primaCattura}</td>
                        <td>${mediaIncontri}</td>
                    </tr>
                `;
                
             if (rank === 1) {
                    $(".leaderboard-table thead tr").html(`
                        <th class="rank">Pos.</th>
                        <th>Utente</th>
                        <th>Shinies</th>
                        <th>Pokémon Unici</th>
                        <th>Prima Cattura</th>
                        <th>Media Incontri</th>
                    `);
                }
            }
            
            $(".leaderboard-table tbody").append(rowHtml);
        });
    }
    
    // Funzione per formattare le date
    function formatDate(dateString) {
        let date = new Date(dateString);
        return date.getDate().toString().padStart(2, '0') + '/' + 
               (date.getMonth() + 1).toString().padStart(2, '0') + '/' + 
               date.getFullYear();
    }
    
    // Carica la classifica iniziale
    loadLeaderboard("Più Shinies");
}); 