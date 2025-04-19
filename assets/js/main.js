

$(document).ready(function() {
    console.log("Document ready!");
    
    // PARTE 1 su aiuto: Ricerca di questi mostriciattoli tascabili comunemente conosciuti come Pokemon
    let pokemonList = []; 
    let validPokemonNames = []; 
    let timeoutId;
    
    console.log("Caricamento Pokémon...");
    
    // Carica la lista dei pokémon all'avvio
    $.ajax({
        url: 'https://pokeapi.co/api/v2/pokemon?limit=1200', 
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            console.log("Pokémon caricati:", data.results.length);
            pokemonList = data.results;
            
            // Crea l'elenco dei nomi validi
            validPokemonNames = pokemonList.map(function(pokemon) {
                return pokemon.name.charAt(0).toUpperCase() + pokemon.name.slice(1);
            });
        },
    });
    
    // Gestisce l'input dell'utente che malcapitatamente ha deciso di usare questo sito
    $('#pokemon-search').on('input', function() {
        console.log("Input ricevuto:", $(this).val());
        let query = $(this).val().toLowerCase();
        let resultsContainer = $('#pokemon-results');
        
        clearTimeout(timeoutId);
        
        if (query.length < 2) {
            resultsContainer.hide().empty();
            return;
        }
        
        // Cerca dopo un breve ritardo per evitare troppe richieste (certo come no)
        timeoutId = setTimeout(function() {
            console.log("Ricerca di:", query);
            
            let filteredPokemon = pokemonList.filter(function(pokemon) {
                return pokemon.name.includes(query);
            }).slice(0, 10); // Limita a 10 risultati
            
            console.log("Risultati trovati:", filteredPokemon.length);
            
            if (filteredPokemon.length > 0) {
                resultsContainer.empty();
                
                // Aggiungi ogni pokémon ai risultati
                filteredPokemon.forEach(function(pokemon) {
                    let pokemonName = pokemon.name;
                    let pokemonUrl = pokemon.url;
                    let pokemonId = pokemonUrl.split('/').filter(Boolean).pop();
                    let capitalizedName = pokemonName.charAt(0).toUpperCase() + pokemonName.slice(1);
                    //immaginina per il nostro pokimans
                    let imageUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' + pokemonId + '.png';
                    
                    let item = $('<div class="autocomplete-item"></div>')
                        .append('<img src="' + imageUrl + '" alt="' + capitalizedName + '">')
                        .append('<span class="pokemon-name">' + capitalizedName + '</span>')
                        .append('<span class="pokemon-number">#' + pokemonId + '</span>');
                    
                    item.on('click', function() {
                        $('#pokemon-search').val(capitalizedName);
                        $('#pokemon-search').attr('data-valid', 'true');
                        resultsContainer.hide().empty();
                        
                        // Cambia lo stile per indicare che è valido
                        $('#pokemon-search').removeClass('is-invalid').addClass('is-valid');
                    });
                    
                    resultsContainer.append(item);
                });
                
                resultsContainer.show();
            } else {
                resultsContainer.hide().empty();
            }
        }, 300);
        
        // Marca il campo come non valido se viene modificato
        $(this).attr('data-valid', 'false');
        $(this).removeClass('is-valid').addClass('is-invalid');
    });
    
    // Nascondi i risultati quando si clicca altrove
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-container').length) {
            $('#pokemon-results').hide().empty();
        }
    });
    
    // Validazione form prima dell'invio
    $('#new-hunt-form').on('submit', function(e) {
        let pokemonName = $('#pokemon-search').val();
        let isValid = false;
        
        // Verifica se il nome è nell'elenco dei Pokémon validi
        if ($('#pokemon-search').attr('data-valid') === 'true') {
            isValid = true;
        } else {
            // Controlla anche se il nome corrisponde esattamente a un Pokémon valido (no non si può cercare Pippo baudo shiny)
            isValid = validPokemonNames.includes(pokemonName);
            if (isValid) {
                $('#pokemon-search').attr('data-valid', 'true');
                $('#pokemon-search').removeClass('is-invalid').addClass('is-valid');
            }
        }
        
        if (!isValid) {
            e.preventDefault(); // Impedisci l'invio del form
            alert('Seleziona un Pokémon valido dall\'elenco!');
            $('#pokemon-search').focus();
            return false;
        }
        
        return true;
    });
    
    // PARTE Bo mi sono perso: Selezione di una nuova caccia
    // Abilita il select dei giochi solo quando viene scelto un Pokémon valido
    $('#pokemon-search').on('change', function() {
        if ($(this).attr('data-valid') === 'true') {
            $('#game-select').prop('disabled', false);
        }
    });
    
    // Quando viene selezionata una voce dall'autocompletamento
    $(document).on('click', '.autocomplete-item', function() {
        // Abilita il select dei giochi
        $('#game-select').prop('disabled', false);
    });
    
    // Quando viene selezionato un gioco, carica i metodi disponibili
    $('#game-select').on('change', function() {
        let gameId = $(this).val();
        
        // Resetta il select dei metodi
        $('#method-select').empty().append('<option value="">Seleziona metodo</option>').prop('disabled', true);
        
        if (gameId) {
            // Carica i metodi disponibili per questo gioco
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    ajax_action: 'get_methods',
                    game_id: gameId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.methods.length > 0) {
                        // Aggiungi i metodi al select
                        $.each(response.methods, function(i, method) {
                            $('#method-select').append('<option value="' + method.id + '">' + method.nome + '</option>');
                        });
                        
                        // Abilita il select dei metodi
                        $('#method-select').prop('disabled', false);
                    } else {
                        alert('Nessun metodo disponibile per questo gioco.');
                    }
                },
                error: function() {
                    alert('Si è verificato un errore durante il caricamento dei metodi.');
                }
            });
        }
    });
    
    // PARTE 14.550: Funzionalità AJAX per i contatori (finalmente la parte importante)
    let huntData = {};
    
    // Inizializza i tempi di incremento usando la data di inizio della caccia
    function initializeHuntData() {
        $('.timer').each(function() {
            const timerId = $(this).attr('id');
            const huntId = timerId.replace('timer-', '');
            const startDate = new Date($(this).data('start'));
            
            // Inizializza i dati della caccia
            huntData[huntId] = huntData[huntId] || {};
            huntData[huntId].lastIncrementTime = startDate; 
        });
    }
    
    // Inizializza i dati all'avvio
    $(document).ready(function() {
        initializeHuntData();
    });
    
    // Incremento contatore via AJAX
    $(".increment-btn").on('click', function() {
        let huntId = $(this).data('hunt-id');
        let increment = $("#increment-" + huntId).val();
        let counterElem = $("#counter-" + huntId);
        
        // Calcola il tempo trascorso dall'ultimo incremento
        let now = new Date();
        let timeSinceLastIncrement = 0;
        
        if (huntData[huntId] && huntData[huntId].lastIncrementTime) {
            timeSinceLastIncrement = Math.floor((now - huntData[huntId].lastIncrementTime) / 1000);
        }
        
        // Aggiorna il tempo dell'ultimo incremento
        huntData[huntId] = huntData[huntId] || {};
        huntData[huntId].lastIncrementTime = now;
        huntData[huntId].lastIncrementDuration = timeSinceLastIncrement;
        
        // Mostra il tempo dell'ultimo incontro
        updateLastEncounterTime(huntId);
        
        // Esegue l'incremento
        incrementCounter(huntId, increment);
    });
    
    // Funzione per aggiornare il tempo dell'ultimo incontro
    function updateLastEncounterTime(huntId) {
        if (huntData[huntId] && huntData[huntId].lastIncrementDuration) {
            let seconds = huntData[huntId].lastIncrementDuration;
            let minutes = Math.floor(seconds / 60);
            seconds = seconds % 60;
            
            // Formatta il tempo
            let formattedTime = '';
            if (minutes > 0) {
                formattedTime = minutes + 'm ' + seconds + 's';
            } else {
                formattedTime = seconds + 's';
            }
            
            // Aggiorna l'elemento del tempo dell'ultimo incontro
            $('#last-encounter-' + huntId + ' span').text(formattedTime);
        }
    }
    
    // Funzione per incrementare il contatore
    function incrementCounter(huntId, increment) {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                ajax_action: 'increment',
                hunt_id: huntId,
                increment: increment
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Aggiorna il contatore senza ricaricare la pagina (yuppi)
                    $("#counter-" + huntId).text(new Intl.NumberFormat().format(response.tentativi));
                    $("#counter-" + huntId).addClass('text-success');
                    setTimeout(function() {
                        $("#counter-" + huntId).removeClass('text-success');
                    }, 500);
                } else {
                    alert('Errore: ' + response.message);
                }
            },
            error: function() {
                alert('Si è verificato un errore durante la richiesta.');//speriamo di no
            }
        });
    }
    
    // Gestione del pulsante di incremento automatico
    $(document).on('click', '.auto-increment', function() {
        const huntId = $(this).data('hunt-id');
        
        // Controlla se l'incremento automatico è già attivo
        if ($(this).hasClass('active')) {
            // Disattiva l'incremento automatico
            $(this).removeClass('active');
            $(this).html('<i class="fas fa-sync-alt"></i>');
            $(this).attr('title', 'Auto');
            
            // Ferma l'intervallo
            if (huntData[huntId] && huntData[huntId].autoIncrementInterval) {
                clearInterval(huntData[huntId].autoIncrementInterval);
                huntData[huntId].autoIncrementInterval = null;
            }
        } else {
            // Attiva l'incremento automatico solo se c'è stato almeno un incremento manuale
            if (huntData[huntId] && huntData[huntId].lastIncrementDuration && huntData[huntId].lastIncrementDuration > 0) {
                // Attiva l'incremento automatico
                $(this).addClass('active');
                $(this).html('<i class="fas fa-stop"></i>');
                $(this).attr('title', 'Stop Auto');
                
                // Calcola l'intervallo basato sull'ultimo incremento (in millisecondi)
                const intervalTime = huntData[huntId].lastIncrementDuration * 1000;
                
                // Imposta l'intervallo per l'incremento automatico
                huntData[huntId].autoIncrementInterval = setInterval(function() {
                    // Verifica se l'incremento automatico è ancora attivo
                    if ($('.auto-increment[data-hunt-id="' + huntId + '"]').hasClass('active')) {
                        // Incrementa il contatore
                        incrementCounter(huntId, 1);
                    } else {
                        // Ferma l'intervallo se l'incremento automatico è stato disattivato
                        clearInterval(huntData[huntId].autoIncrementInterval);
                    }
                }, intervalTime);
            } else {
                alert('Devi prima incrementare manualmente almeno una volta per stabilire l\'intervallo di tempo.');
            }
        }
    });
    
    // Completamento caccia 
    $(".complete-btn").on('click', function() {
        let huntId = $(this).data('hunt-id');
        let card = $(this).closest('.hunt-card');
        let pokemonCard = card.find('.pokemon-card');
        
        // Disabilita il pulsante per evitare doppi clic
        $(this).prop('disabled', true);
        
        // Applica l'animazione di brillantezza super incredibilmente ganza
        pokemonCard.addClass('shiny-animation');
        
        // Crea effetto confetti
        for (let i = 0; i < 30; i++) {
            let confetti = $('<div class="confetti"></div>');
            let size = Math.random() * 8 + 5;
            let colors = ['#f8d568', '#ffd700', '#6c9ff8', '#ff6b6b', '#48cfad'];
            
            confetti.css({
                'width': size + 'px',
                'height': size + 'px',
                'background-color': colors[Math.floor(Math.random() * colors.length)],
                'left': Math.random() * 100 + '%',
                'top': Math.random() * 100 + '%',
                'position': 'absolute'
            });
            
            pokemonCard.append(confetti);
            
            // Anima ogni pezzo di confetti
            confetti.animate({
                opacity: 1,
                top: '-=' + (Math.random() * 100),
                left: '+=' + (Math.random() * 60 - 30)
            }, 700 + Math.random() * 300);
        }
        
        // Dopo l'animazione, invia la richiesta AJAX
        setTimeout(function() {
            $.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    ajax_action: 'complete',
                    hunt_id: huntId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        card.fadeOut(500, function() {
                            $(this).remove();

                            // Aggiorna statistiche
                            var huntingValue = parseInt($('.sidebar-stats .stat-value').first().text());
                            var collectedValue = parseInt($('.sidebar-stats .stat-value').eq(1).text());
                            
                            $('.sidebar-stats .stat-value').first().text(huntingValue - 1);
                            $('.sidebar-stats .stat-value').eq(1).text(collectedValue + 1);
                        });
                    } else {
                        alert('Errore: ' + response.message);
                        $(this).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Si è verificato un errore durante la richiesta.');
                    $(this).prop('disabled', false);
                }
            });
        }, 1700);
    });
    
    // PARTE Lorenzo Cara è un fascista: Ricerca nelle cacce esistenti
    $("#searchHunt").on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $(".hunt-card").each(function() {
            var pokemon = $(this).data('pokemon');
            var method = $(this).data('method');
            
            if (pokemon.includes(searchTerm) || method.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // PARTE 2 ( ah ): Carica immagini shiny dei Pokémon nelle schede di caccia
    function loadShinyImages() {
        $('.pokemon-img').each(function() {
            let img = $(this);
            let pokemonName = img.data('pokemon').toLowerCase();
            
            // Prima cerca l'ID del Pokémon dal nome
            $.ajax({
                url: 'https://pokeapi.co/api/v2/pokemon/' + pokemonName,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let pokemonId = data.id;
                    // Imposta l'immagine shiny
                    let shinyUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/shiny/' + pokemonId + '.png';
                    img.attr('src', shinyUrl);
                },
                error: function(xhr, status, error) {
                    // In caso di errore, prova a usare un'immagine generica, sempre di pokimans ovviamente
                    img.attr('src', 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' + Math.floor(Math.random() * 898 + 1) + '.png');
                }
            });
        });
    }
    
    // Carica le immagini quando la pagina è pronta
    loadShinyImages();
    
    // PARTE forse ci sono troppe aprti: Timer per le cacce
    let pausedTimers = {}; // Oggetto per tenere traccia dei timer in pausa e del tempo trascorso
    
    // Gestione del pulsante pausa
    $(document).on('click', '.pause-timer', function() {
        const huntId = $(this).data('hunt-id');
        const timerEl = $('#timer-' + huntId);
        const now = new Date();
        
        // Se il timer esiste già nell'oggetto
        if (pausedTimers[huntId]) {
            if (pausedTimers[huntId].isPaused) {
                // RIPRENDI il timer dalla pausa
                pausedTimers[huntId].isPaused = false;
                pausedTimers[huntId].resumeTime = now;
                $(this).html('<i class="fas fa-pause"></i>');
                $(this).attr('title', 'Pausa');
            } else {
                // METTI IN PAUSA un timer che era stato ripreso
                // Calcola il tempo trascorso dalla ripresa
                const secondsSinceResume = Math.floor((now - pausedTimers[huntId].resumeTime) / 1000);
                // Aggiungi questo tempo al totale accumulato
                pausedTimers[huntId].totalSeconds += secondsSinceResume;
                pausedTimers[huntId].isPaused = true;
                pausedTimers[huntId].pauseTime = now;
                pausedTimers[huntId].resumeTime = null;
                $(this).html('<i class="fas fa-play"></i>');
                $(this).attr('title', 'Riprendi');
            }
        } else {
            // Prima pausa per questo timer
            const startDate = new Date(timerEl.data('start'));
            const elapsedSeconds = Math.floor((now - startDate) / 1000);
            
            pausedTimers[huntId] = {
                isPaused: true,
                totalSeconds: elapsedSeconds,  // Tempo totale accumulato
                pauseTime: now,
                resumeTime: null,
                initialStart: startDate
            };
            
            $(this).html('<i class="fas fa-play"></i>');
            $(this).attr('title', 'Riprendi');
        }
        
        // Forza un aggiornamento immediato del display
        updateTimer(timerEl, huntId);
    });
    
    // Funzione per aggiornare un singolo timer
    function updateTimer(timerEl, huntId) {
        let timeString = '';
        
        if (pausedTimers[huntId] && pausedTimers[huntId].isPaused) {
            // Timer in pausa - mostra il tempo accumulato
            timeString = formatTimeString(pausedTimers[huntId].totalSeconds);
        } else if (pausedTimers[huntId] && !pausedTimers[huntId].isPaused) {
            // Timer ripreso - calcola tempo accumulato + tempo dopo la ripresa
            const now = new Date();
            const secondsSinceResume = Math.floor((now - pausedTimers[huntId].resumeTime) / 1000);
            const totalSeconds = pausedTimers[huntId].totalSeconds + secondsSinceResume;
            timeString = formatTimeString(totalSeconds);
        } else {
            // Timer mai messo in pausa
            const startDate = new Date(timerEl.data('start'));
            const now = new Date();
            const totalSeconds = Math.floor((now - startDate) / 1000);
            timeString = formatTimeString(totalSeconds);
        }
        
        // Aggiorna il timer
        timerEl.find('span').text(timeString);
    }
    
    // Funzione che aggiorna tutti i timer
    function updateTimers() {
        $('.timer').each(function() {
            const timer = $(this);
            const huntId = timer.attr('id').replace('timer-', '');
            updateTimer(timer, huntId);
        });
    }
    
    // Funzione per formattare il tempo in giorni, ore, minuti e secondi
    function formatTimeString(totalSeconds) {
        // Calcola giorni, ore, minuti e secondi
        let days = Math.floor(totalSeconds / (60 * 60 * 24));
        totalSeconds -= days * 60 * 60 * 24;
        let hours = Math.floor(totalSeconds / (60 * 60));
        totalSeconds -= hours * 60 * 60;
        let minutes = Math.floor(totalSeconds / 60);
        totalSeconds -= minutes * 60;
        let seconds = totalSeconds;
        
        // Formatta il tempo
        let timeString = '';
        if (days > 0) {
            timeString += days + 'g ';
        }
        timeString += (hours < 10 ? '0' : '') + hours + ':';
        timeString += (minutes < 10 ? '0' : '') + minutes + ':';
        timeString += (seconds < 10 ? '0' : '') + seconds;
        
        return timeString;
    }//mannaggia a sto timer 
    
    // Aggiorna i timer subito e poi ogni secondo
    updateTimers();
    setInterval(updateTimers, 1000);
    
    // PARTE QUATTRO GIALLO: Filtri per le cacce
    $('.filter-buttons button').on('click', function() {
        // Rimuovi la classe active da tutti i pulsanti
        $('.filter-buttons button').removeClass('active');
        // Aggiungi la classe active al pulsante cliccato
        $(this).addClass('active');
        
        const filterType = $(this).text().toLowerCase();
        
        // Mostra tutte le cacce
        $('.hunt-card').show();
        
        // Applica il filtro in base al tipo
        switch (filterType) {
            case 'tutti':
                // Mostra tutte (già fatto)
                break;
                
            case 'recenti':
                // Ordina per data di inizio (più recenti prima)
                sortHuntsByDate(true);
                break;
                
            case 'meno recenti':
                // Ordina per data di inizio (più vecchie prima)
                sortHuntsByDate(false);
                break;
                
            case 'per gioco':
                // Mostra il selettore di giochi
                showGameFilter();
                break;
                
            case 'per metodo':
                // Mostra il selettore di metodi
                showMethodFilter();
                break;
        }
    });
    
    // Funzione per ordinare le cacce per data
    function sortHuntsByDate(descending = false) {
        const huntsContainer = $('.row:has(.hunt-card)');
        const huntCards = $('.hunt-card').detach().get();
        
        // Ordina le carte in base alla data di inizio
        huntCards.sort(function(a, b) {
            const dateA = new Date($(a).find('.timer').data('start'));
            const dateB = new Date($(b).find('.timer').data('start'));
            
            return descending ? dateB - dateA : dateA - dateB;
        });
        
        $.each(huntCards, function(idx, card) {
            huntsContainer.append(card);
        });
    }
    
    // Crea e mostra il filtro per giochi
    function showGameFilter() {
        // Rimuovi filtri esistenti
        removeExistingFilters();
        
        // Mostra indicatore di caricamento
        $('.filter-dropdown-container').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Caricamento giochi...</div>');
        
        // Ottieni tutti i giochi disponibili con una chiamata AJAX
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                ajax_action: 'get_all_games'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.games) {
                    // Crea il selettore di giochi, 
                    const filterContainer = $('<div class="game-filter-container"></div>');
                    const selectGame = $('<select class="form-control bg-dark text-white" style="width: 250px;"></select>');
                    
                    selectGame.append('<option value="">Seleziona un gioco</option>');
                    
                    // Aggiungi le opzioni per ogni gioco
                    $.each(response.games, function(i, game) {
                        selectGame.append(`<option value="${game.id}">${game.nome}</option>`);
                    });
                    
                    selectGame.on('change', function() {
                        const selectedGameId = $(this).val();
                        
                        if (selectedGameId) {
                            // Nascondi tutte le cacce inizialmente
                            $('.hunt-card').hide();
                            $('.no-results-message').remove();
                            
                            // Ottieni il nome del gioco selezionato
                            const selectedGame = $(this).find('option:selected').text();
                            let foundCards = false;
                            
                            // Fa una chiamata Ajax per ottenere le cacce con questo ID di gioco
                            $.ajax({
                                url: 'index.php',
                                type: 'POST',
                                data: {
                                    ajax_action: 'filter_hunts_by_game',
                                    game_id: selectedGameId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success && response.hunt_ids && response.hunt_ids.length > 0) {
                                        // Mostra le cacce con gli ID restituiti
                                        response.hunt_ids.forEach(function(huntId) {
                                            // Cambiato il selettore per trovare le cacce
                                            $('.hunt-card').each(function() {
                                                if ($(this).find('.complete-btn').data('hunt-id') == huntId) {
                                                    $(this).show();
                                                    foundCards = true;
                                                }
                                            });
                                        });
                                        
                                        // Se non abbiamo trovato carte, mostra un messaggio
                                        if (!foundCards && $('.no-results-message').length === 0) {
                                            $('.row:has(.hunt-card)').append('<div class="col-12 no-results-message alert alert-info mt-3">Nessuna caccia trovata per questo gioco</div>');
                                        }
                                    } else {
                                        // Se non ci sono risultati, mostra un messaggio
                                        if ($('.no-results-message').length === 0) {
                                            $('.row:has(.hunt-card)').append('<div class="col-12 no-results-message alert alert-info mt-3">Nessuna caccia trovata per questo gioco</div>');
                                        }
                                    }
                                },
                                error: function() {
                                    // In caso di errore, mostra tutte le carte
                                    $('.hunt-card').show();
                                    $('.no-results-message').remove();
                                }
                            });
                        } else {
                            // Se non è selezionato nessun gioco, mostra tutte le cacce
                            $('.hunt-card').show();
                            // Rimuovi eventuali messaggi di nessun risultato
                            $('.no-results-message').remove();
                        }
                    });
                    
                    filterContainer.append(selectGame);
                    $('.filter-dropdown-container').html(filterContainer);
                } else {
                    $('.filter-dropdown-container').html('<div class="alert alert-warning">Impossibile caricare i giochi.</div>');
                }
            },
            error: function() {
                $('.filter-dropdown-container').html('<div class="alert alert-danger">Errore durante il caricamento dei giochi.</div>');
            }
        });
    }
    
    // Crea e mostra il filtro per metodi
    function showMethodFilter() {
        // Rimuovi filtri esistenti
        removeExistingFilters();
        
        // Mostra indicatore di caricamento
        $('.filter-dropdown-container').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Caricamento metodi...</div>');
        
        // Ottieni tutti i metodi disponibili con una chiamata AJAX
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                ajax_action: 'get_all_methods'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.methods) {
                    // Crea il selettore di metodi con posizionamento esplicito
                    const filterContainer = $('<div class="method-filter-container"></div>');
                    const selectMethod = $('<select class="form-control bg-dark text-white" style="width: 250px;"></select>');
                    
                    selectMethod.append('<option value="">Seleziona un metodo</option>');
                    
                    // Aggiungi le opzioni per ogni metodo
                    $.each(response.methods, function(i, method) {
                        selectMethod.append(`<option value="${method.id}">${method.nome}</option>`);
                    });
                    
                    // Event listener per il cambio di metodo
                    selectMethod.on('change', function() {
                        const selectedMethodId = $(this).val();
                        
                        if (selectedMethodId) {
                            // Nascondi tutte le cacce inizialmente (non sia mai che poi succedono cose)
                            $('.hunt-card').hide();
                            $('.no-results-message').remove();
                            
                            // Fa una chiamata Ajax per ottenere le cacce con questo ID di metodo
                            $.ajax({
                                url: 'index.php',
                                type: 'POST',
                                data: {
                                    ajax_action: 'filter_hunts_by_method',
                                    method_id: selectedMethodId
                                },
                                dataType: 'json',
                                success: function(response) {
                                    console.log('Risposta ricevuta:', response);
                                    if (response.success && response.hunt_ids && response.hunt_ids.length > 0) {
                                        // Mostra le cacce con gli ID restituiti
                                        let foundCards = false;
                                        response.hunt_ids.forEach(function(huntId) {
                                            // Cambiato il selettore per trovare le cacce
                                            $('.hunt-card').each(function() {
                                                if ($(this).find('.complete-btn').data('hunt-id') == huntId) {
                                                    $(this).show();
                                                    foundCards = true;
                                                }
                                            });
                                        });
                                        
                                        // Se non abbiamo trovato carte, mostra un messaggio
                                        if (!foundCards && $('.no-results-message').length === 0) {
                                            $('.row:has(.hunt-card)').append('<div class="col-12 no-results-message alert alert-info mt-3">Nessuna caccia trovata per questo metodo</div>');
                                        }
                                    } else {
                                        // Se non ci sono risultati, mostra un messaggio
                                        if ($('.no-results-message').length === 0) {
                                            $('.row:has(.hunt-card)').append('<div class="col-12 no-results-message alert alert-info mt-3">Nessuna caccia trovata per questo metodo</div>');
                                        }
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.log('Si è verificato un errore:', error);
                                    console.log('Status:', status);
                                    // In caso di errore, mostra tutte le carte
                                    $('.hunt-card').show();
                                    $('.no-results-message').remove();
                                }
                            });
                        } else {
                            // Se non è selezionato nessun metodo, mostra tutte le cacce
                            $('.hunt-card').show();
                            // Rimuovi eventuali messaggi di nessun risultato
                            $('.no-results-message').remove();
                        }
                    });
                    
                    filterContainer.append(selectMethod);
                    $('.filter-dropdown-container').html(filterContainer);
                } else {
                    $('.filter-dropdown-container').html('<div class="alert alert-warning">Impossibile caricare i metodi.</div>');
                }
            },
            error: function() {
                $('.filter-dropdown-container').html('<div class="alert alert-danger">Errore durante il caricamento dei metodi.</div>');
            }
        });
    }
    
    // Rimuove i filtri esistenti
    function removeExistingFilters() {
        $('.filter-dropdown-container').empty();
        $('.no-results-message').remove();
    }
    
    // PARTE tema: Gestione cambio tema
    $('.theme-toggle').on('click', function() {
        let currentPage = window.location.pathname.split('/').pop() || 'index.php';
        
        $.ajax({
            url: currentPage,
            type: 'POST',
            data: {
                toggle_theme: true
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.theme === 'light') {
                        $('body').addClass('light-theme');
                        $('.theme-toggle i').removeClass('fa-sun').addClass('fa-moon');
                    } else {
                        $('body').removeClass('light-theme');
                        $('.theme-toggle i').removeClass('fa-moon').addClass('fa-sun');
                    }
                }
            }
        });
    });
}); 