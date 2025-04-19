$(document).ready(function() {
    console.log("Inizializzazione della pagina collection");
    
    // Funzione per caricare le immagini shiny dei Pokémon
    function loadShinyImages() {
        console.log("Caricamento immagini shiny");
        $('.pokemon-img').each(function() {
            let img = $(this);
            let pokemonName = img.data('pokemon').toLowerCase().trim();
            
            if (!pokemonName) return;
            
            if (pokemonName.includes('-')) {
                // Per forme speciali come "charizard-mega" o "Cappuccino-assassino"
                pokemonName = pokemonName.split('-')[0];
            }
            

            $.ajax({
                url: 'https://pokeapi.co/api/v2/pokemon/' + pokemonName,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let pokemonId = data.id;
                    // Imposta l'immagine shiny
                    let shinyUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/shiny/' + pokemonId + '.png';
                    
                    // Verifica che l'immagine esista prima di impostarla
                    let testImage = new Image();
                    testImage.onload = function() {
                        img.attr('src', shinyUrl);
                    };
                    testImage.onerror = function() {
                        // Se non c'è l'immagine shiny, usa quella normale
                        let normalUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' + pokemonId + '.png';
                        img.attr('src', normalUrl);
                    };
                    testImage.src = shinyUrl;
                },
                error: function(xhr, status, error) {
                    //si sceglie una immagine a caso tanto daje
                    img.attr('src', 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' + Math.floor(Math.random() * 898 + 1) + '.png');
                }
            });
        });
    }
    
    window.loadShinyImages = loadShinyImages;
    
    // Carica le immagini quando la pagina è pronta
    setTimeout(function() {
        loadShinyImages();
    }, 500);
    
    // Applica il filtro 'Tutti' all'avvio della pagina
    setTimeout(function() {
        console.log("Caricamento filtro iniziale 'all'");
        loadPokemon('all');
    }, 100);
    
    // Funzionalità di ricerca
    $("input[placeholder='Cerca...']").on('keyup', function() {
        let searchTerm = $(this).val().toLowerCase();
        
        $(".pokemon-card").each(function() {
            let cardContent = $(this).text().toLowerCase();
            
            if (cardContent.includes(searchTerm)) {
                $(this).parent().show();
            } else {
                $(this).parent().hide();
            }
        });
    });
    
    // Gestione dei filtri
    $(".filter-buttons button").off('click').on('click', function() {
        $(".filter-buttons button").removeClass('active');
        $(this).addClass('active');
        let filterType = $(this).text();
        console.log("Filtro selezionato:", filterType);
        

        $(".group-header").remove();
        
        // Determina il tipo di filtro e chiama la funzione appropriata
        switch(filterType) {
            case "Tutti":
                loadPokemon('all');
                break;
                
            case "Recenti":
                loadPokemon('recent');
                break;
                
            case "Meno Recenti":
                loadPokemon('oldest');
                break;
                
            case "Per Gioco":
                loadPokemon('by_game');
                break;
                
            case "Per Metodo":
                loadPokemon('by_method');
                break;
            
            default:
                console.log("Filtro non riconosciuto:", filterType);
                loadPokemon('all');
                break;
        }
    });
    
    // Funzione per caricare i Pokémon con il filtro selezionato
    function loadPokemon(filter) {
        console.log("Caricamento Pokémon con filtro:", filter);
        
        // Mostra un indicatore di caricamento
        $(".row").html('<div class="col-12 text-center"><p><i class="fas fa-spinner fa-spin"></i> Caricamento in corso...</p></div>');
        
        const validFilters = ['all', 'recent', 'oldest', 'by_game', 'by_method'];
        if (!validFilters.includes(filter)) {
            console.warn("Filtro non valido:", filter, "Uso 'all' come default");
            filter = 'all';
        }
        
        $.ajax({
            url: 'collection.php',
            type: 'POST',
            data: {
                ajax_action: 'filter_pokemon',
                filter: filter
            },
            dataType: 'json',
            success: function(response) {
                console.log("Risposta ricevuta:", response);
                if (response && response.success === true && Array.isArray(response.data)) {
                    updatePokemonDisplay(response.data, filter);
                } else {
                    let errorMsg = response && response.message ? response.message : "Risposta non valida dal server";
                    $(".row").html('<div class="col-12 text-center"><p>Si è verificato un errore: ' + errorMsg + '</p></div>');
                    console.error("Errore nella risposta:", response);
                }
            },
        });
    }
    
    // Funzione per aggiornare la visualizzazione dei Pokémon
    function updatePokemonDisplay(data, filter) {
        console.log("Aggiornamento visualizzazione con filtro:", filter, "dati:", data);
        
        let container = $(".row");
        container.empty();
        
        if (!data || data.length === 0) {
            container.html('<div class="col-12 text-center"><p>Nessun Pokémon trovato.</p></div>');
            return;
        }
        
        if (filter === 'by_game' || filter === 'by_method') {
            let groups = {};
            
            // Organizza i dati per gruppo
            $.each(data, function(index, pokemon) {
                let groupKey = filter === 'by_game' ? pokemon.nomeGioco : pokemon.nomeMetodo;
                
                if (!groupKey) {
                    console.warn("Pokemon senza " + (filter === 'by_game' ? 'nomeGioco' : 'nomeMetodo'), pokemon);
                    groupKey = "Non specificato";
                }
                
                if (!groups[groupKey]) {
                    groups[groupKey] = [];
                }
                
                groups[groupKey].push(pokemon);
            });
            
            // Ordina le chiavi dei gruppi alfabeticamente
            let sortedKeys = Object.keys(groups).sort();
            
            // Aggiungi ogni gruppo al container
            $.each(sortedKeys, function(index, key) {
                // Aggiungi intestazione del gruppo
                container.append('<div class="col-12 group-header"><h4 class="mt-4 mb-3">' + key + '</h4></div>');
                
                // Crea una riga per questo gruppo
                let groupRow = $('<div class="row w-100"></div>');
                container.append(groupRow);
                
                // Aggiungi tutti i Pokémon del gruppo
                $.each(groups[key], function(i, pokemon) {
                    try {
                        let card = createPokemonCard(pokemon);
                        groupRow.append(card);
                    } catch (error) {
                        console.error("Errore nella creazione della card per", pokemon, error);
                        groupRow.append('<div class="col-md-3"><div class="pokemon-card">Errore nel caricamento</div></div>');
                    }
                });
            });
        } else {
        
            let mainRow = $('<div class="row w-100"></div>');
            container.append(mainRow);
            
            $.each(data, function(index, pokemon) {
                try {
                    let card = createPokemonCard(pokemon);
                    mainRow.append(card);
                } catch (error) {
                    console.error("Errore nella creazione della card per", pokemon, error);
                    mainRow.append('<div class="col-md-3"><div class="pokemon-card">Errore nel caricamento</div></div>');
                }
            });
        }
  
        loadShinyImages();
        $('[data-toggle="dropdown"]').dropdown();

        $('[data-toggle="dropdown"]').dropdown();
    }
    $(document).ready(function() {
        // Inizializza tutti i dropdown
        $('[data-toggle="dropdown"]').dropdown();
        
        // Gestione eliminazione caccia via AJAX
        $(document).on('click', '.dropdown-item[name="delete_hunt"]', function(e) {
            e.preventDefault();
            var huntId = $(this).closest('form').find('input[name="hunt_id"]').val();
            var card = $(this).closest('.col-md-3');
            
            $.ajax({
                url: 'collection.php',
                type: 'POST',
                data: {
                    ajax_action: 'delete_hunt',
                    hunt_id: huntId
                },
                success: function(response) {
                    if (response.success) {
                        card.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Se non ci sono più card, mostra un messaggio
                            if ($('.pokemon-card').length === 0) {
                                $(".row").html('<div class="col-12 text-center"><p>Non hai ancora completato nessuna caccia.</p></div>');
                            }
                        });
                    } else {
                        alert('Errore durante l\'eliminazione della caccia: ' + response.message);
                    }
                },
                error: function() {
                    alert('Errore durante l\'eliminazione della caccia');
                }
            });
        });
        
        // Gestione click su Dettagli
        $(document).on('click', '.dropdown-item[href="#"]', function(e) {
            e.preventDefault();
            var huntId = $(this).data('hunt-id');
            
            // Carica i dettagli della caccia
            $.ajax({
                url: 'collection.php',
                type: 'POST',
                data: {
                    ajax_action: 'get_hunt_details',
                    hunt_id: huntId
                },
                success: function(response) {
                    if (response.success) {
                        // Popola il modal con i dettagli
                        $('#editHuntId').val(response.hunt.idCaccia);
                        $('#editPokemonName').val(response.hunt.pokemon);
                        $('#editPokemonName').attr('data-valid', 'true').removeClass('is-invalid').addClass('is-valid');
                        
                        // Popola il select dei giochi
                        var gameSelect = $('#editGameSelect');
                        gameSelect.empty();
                        $.each(response.games, function(i, game) {
                            var option = $('<option></option>')
                                .attr('value', game.idGioco)
                                .text(game.nome);
                            
                            if (game.idGioco == response.hunt.giocoId) {
                                option.attr('selected', 'selected');
                            }
                            
                            gameSelect.append(option);
                        });
                        
                        // Popola il select dei metodi
                        var methodSelect = $('#editMethodSelect');
                        methodSelect.empty();
                        $.each(response.methods, function(i, method) {
                            var option = $('<option></option>')
                                .attr('value', method.idMetodo)
                                .text(method.nome);
                            
                            if (method.idMetodo == response.hunt.metodoId) {
                                option.attr('selected', 'selected');
                            }
                            
                            methodSelect.append(option);
                        });
                        
                        // Imposta il numero di incontri
                        $('#editEncounters').val(response.hunt.tentativi);
                        
                        // Formatta la data di completamento per l'input date (YYYY-MM-DD)
                        var completionDate = new Date(response.hunt.DataFine);
                        var formattedDate = completionDate.toISOString().split('T')[0];
                        $('#editCompletionDate').val(formattedDate);
                        
                        // Mostra il modal
                        $('#editHuntModal').modal('show');
                    } else {
                        alert('Errore durante il recupero dei dettagli: ' + response.message);
                    }
                },
                error: function() {
                    alert('Errore di connessione durante il recupero dei dettagli');
                }
            });
        });
        
        // Gestione del salvataggio delle modifiche
        $('#saveHuntChanges').on('click', function() {
            var form = $('#editHuntForm');
            
            // Verifica validità del form e del nome Pokémon
            if (!form[0].checkValidity() || $('#editPokemonName').attr('data-valid') !== 'true') {
                // Form non valido, mostra messaggi di validazione
                form.addClass('was-validated');
                
                if ($('#editPokemonName').attr('data-valid') !== 'true') {
                    $('#editPokemonName').addClass('is-invalid');
                    alert('Seleziona un Pokémon valido dall\'elenco!');
                }
                
                return;
            }
            
            // Raccolta dati
            let huntId = $('#editHuntId').val();
            let pokemonName = $('#editPokemonName').val();
            let gameId = $('#editGameSelect').val();
            let methodId = $('#editMethodSelect').val();
            let encounters = $('#editEncounters').val();
            let completionDate = $('#editCompletionDate').val();
            
            // Invia i dati al server
            $.ajax({
                url: 'collection.php',
                type: 'POST',
                data: {
                    ajax_action: 'update_hunt',
                    hunt_id: huntId,
                    pokemon_name: pokemonName,
                    game_id: gameId,
                    method_id: methodId,
                    encounters: encounters,
                    completion_date: completionDate
                },
                success: function(response) {
                    if (response.success) {
                        // Chiudi il modal
                        $('#editHuntModal').modal('hide');
                        
                        // Aggiorna la card con i nuovi dati
                        var hunt = response.hunt;
                        var card = $('[data-hunt-id="' + huntId + '"]').closest('.col-md-3');
                        
                        card.find('h4').text(hunt.pokemon);
                        card.find('.info:first').text(hunt.nomeGioco);
                        card.find('.badge:eq(0)').text(hunt.nomeMetodo);
                        card.find('.badge:eq(1)').text(hunt.tentativi + ' incontri');
                        card.find('.completion-info small:first').text('Completato il: ' + hunt.dataFine);
                        
                        // Aggiorna l'immagine del Pokémon
                        var pokemonImg = card.find('.pokemon-img');
                        var pokemonName = hunt.pokemon.toLowerCase();
                        
                        // Prima cerca l'ID del Pokémon dal nome per ottenere l'immagine aggiornata
                        $.ajax({
                            url: 'https://pokeapi.co/api/v2/pokemon/' + pokemonName,
                            type: 'GET',
                            dataType: 'json',
                            success: function(data) {
                                var pokemonId = data.id;
                                // Imposta l'immagine shiny con un timestamp per forzare il refresh
                                var shinyUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/shiny/' + pokemonId + '.png?t=' + new Date().getTime();
                                pokemonImg.attr('src', shinyUrl);
                                pokemonImg.attr('data-pokemon', hunt.pokemon);
                                pokemonImg.attr('alt', hunt.pokemon);
                            },
                            error: function() {
                                // Fallback: aggiornamento tramite la funzione generale
                                pokemonImg.attr('data-pokemon', hunt.pokemon);
                                pokemonImg.attr('alt', hunt.pokemon);
                                loadShinyImages();
                            }
                        });
                    } else {
                        alert('Errore durante il salvataggio: ' + response.message);
                    }
                },
                error: function() {
                    alert('Errore di connessione durante il salvataggio');
                }
            });
        });

        // Gestione dell'autocomplete per il nome del Pokémon
        let timeoutId;
        let validPokemonNames = [];
        
        // Carica la lista dei Pokémon all'apertura del modal
        $('#editHuntModal').on('show.bs.modal', function() {
            if (validPokemonNames.length === 0) {
                $.ajax({
                    url: 'https://pokeapi.co/api/v2/pokemon?limit=1200',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        console.log("Pokémon caricati:", data.results.length);
                        let pokemonList = data.results;
                        
                        // Crea l'elenco dei nomi validi
                        validPokemonNames = pokemonList.map(function(pokemon) {
                            return pokemon.name.charAt(0).toUpperCase() + pokemon.name.slice(1);
                        });
                        console.log("Nomi validi caricati:", validPokemonNames.length);
                    },
                    error: function(xhr, status, error) {
                        console.error("Errore nel caricamento dei Pokémon:", error);
                    }
                });
            }
        });
        
        // Gestisci l'input dell'utente
        $('#editPokemonName').on('input', function() {
            let query = $(this).val().toLowerCase();
            let resultsContainer = $('#edit-pokemon-results');
            
            // Pulisci i risultati precedenti
            clearTimeout(timeoutId);
            
            // Non mostrare nulla se l'input è vuoto
            if (query.length < 2) {
                resultsContainer.hide().empty();
                return;
            }
            
            // Cerca dopo un breve ritardo per evitare troppe richieste
            timeoutId = setTimeout(function() {
                // Fetch Pokemon data
                $.ajax({
                    url: 'https://pokeapi.co/api/v2/pokemon?limit=1200',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        let filteredPokemon = data.results.filter(function(pokemon) {
                            return pokemon.name.includes(query);
                        }).slice(0, 10); // Limita a 10 risultati
                        
                        if (filteredPokemon.length > 0) {
                            resultsContainer.empty();
                            
                            // Aggiungi ogni pokémon ai risultati
                            filteredPokemon.forEach(function(pokemon) {
                                let pokemonName = pokemon.name;
                                let pokemonUrl = pokemon.url;
                                let pokemonId = pokemonUrl.split('/').filter(Boolean).pop();
                                let capitalizedName = pokemonName.charAt(0).toUpperCase() + pokemonName.slice(1);
                                
                                let imageUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' + pokemonId + '.png';
                                
                                let item = $('<div class="autocomplete-item"></div>')
                                    .append('<img src="' + imageUrl + '" alt="' + capitalizedName + '">')
                                    .append('<span class="pokemon-name">' + capitalizedName + '</span>')
                                    .append('<span class="pokemon-number">#' + pokemonId + '</span>');
                                
                                item.on('click', function() {
                                    $('#editPokemonName').val(capitalizedName);
                                    $('#editPokemonName').attr('data-valid', 'true');
                                    resultsContainer.hide().empty();
                                    
                                    // Cambia lo stile per indicare che è valido
                                    $('#editPokemonName').removeClass('is-invalid').addClass('is-valid');
                                });
                                
                                resultsContainer.append(item);
                            });
                            
                            resultsContainer.show();
                        } else {
                            resultsContainer.hide().empty();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Errore durante la ricerca:", error);
                    }
                });
            }, 300);
            
            // Marca il campo come non valido se viene modificato
            $(this).attr('data-valid', 'false');
            $(this).removeClass('is-valid').addClass('is-invalid');
        });
        
        // Nascondi i risultati quando si clicca altrove
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.autocomplete-container').length) {
                $('#edit-pokemon-results').hide().empty();
            }
        });
    });

    // Funzione per creare una card Pokémon
    function createPokemonCard(pokemon) {
        let timeString = "";
        
        if (pokemon.intervaloDays > 0 || pokemon.intervaloHours > 0 || pokemon.intervaloMinutes > 0) {
            timeString = (pokemon.intervaloDays > 0 ? pokemon.intervaloDays + 'g ' : '') + 
                         pokemon.intervaloHours + 'h ' + pokemon.intervaloMinutes + 'm';
        } else {
            timeString = "N/A";
        }
        
        return `
            <div class="col-md-3">
                <div class="pokemon-card">
                    <div class="dropdown float-right">
                        <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu-${pokemon.idCaccia}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v text-white"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenu-${pokemon.idCaccia}">
                            <a class="dropdown-item" href="#" data-hunt-id="${pokemon.idCaccia}">Dettagli</a>
                            <form action="collection.php" method="post" class="d-inline">
                                <input type="hidden" name="hunt_id" value="${pokemon.idCaccia}">
                                <button type="button" name="delete_hunt" class="dropdown-item text-danger">Elimina</button>
                            </form>
                        </div>
                    </div>
                    <div class="text-center mb-2">
                        <img class="pokemon-img" src="" alt="${pokemon.pokemon}" data-pokemon="${pokemon.pokemon}">
                    </div>
                    <h4>${pokemon.pokemon}</h4>
                    <div class="info">${pokemon.nomeGioco}</div>
                    <div class="info">
                        <span class="badge">${pokemon.nomeMetodo}</span>
                        <span class="badge">${pokemon.tentativi} incontri</span>
                    </div>
                    <div class="odds-info">
                        <span class="badge badge-primary">
                            <i class="fas fa-dice"></i> ${pokemon.odds || 'Odds N/A'}
                        </span>
                    </div>
                    <div class="completion-info">
                        <small>Completato il: ${pokemon.dataFine}</small><br>
                        <small>Tempo totale: ${timeString}</small>
                    </div>
                </div>
            </div>
        `;
    }
}); 