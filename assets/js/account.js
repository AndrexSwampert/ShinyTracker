$(document).ready(function() {
    console.log("Document ready - account.js caricato");
    
    $(".account-btn").on("click", function(e) {
        e.preventDefault();
        console.log("Clic sul pulsante account");
        
        loadUserData();
        
        $("#accountModal").modal("show");
    });
    var Miaurl = "";
    // Funzione per caricare i dati dell'utente
    function loadUserData() {
        console.log("Caricamento dati utente...");
        const currentPage = window.location.pathname;
    if (currentPage.includes("index.php")) {
        Miaurl = "api/user_account.php";
    } else
    {
        Miaurl = "../api/user_account.php";;
    }   

        $("#userEmail").text("Caricamento...");
        $("#userName").text("Caricamento...");
        $("#accountId").text("Caricamento...");
        $("#accountCreationDate").text("Caricamento...");
        
        $.ajax({
            url: Miaurl,
            type: "POST",
            data: {
                action: "get_user_data"
            },
            dataType: "json",
            success: function(response) {
                console.log("Risposta ricevuta:", response);
                if (response.success) {
                    $("#userEmail").text(response.user_data.email || "Non disponibile");
                    $("#userName").text(response.user_data.username || "Non disponibile");
                    $("#accountId").text(response.user_data.idUtente || "Non disponibile");
                    $("#accountCreationDate").text(response.user_data.registrationDate || "Non disponibile");
                    
                    console.log("Dati utente caricati correttamente");
                } else {
                    console.error("Errore nella risposta:", response.message);
                    showAlert("error", response.message || "Errore durante il recupero dei dati utente");
    
                    $("#userEmail").text("Errore nel caricamento");
                    $("#userName").text("Errore nel caricamento");
                    $("#accountId").text("Errore nel caricamento");
                    $("#accountCreationDate").text("Errore nel caricamento");
                }
            },
            error: function(xhr, status, error) {
                console.error("Errore AJAX durante il caricamento dei dati utente:", status, error);
                console.error("Response Text:", xhr.responseText);
                try {

                    var errorResponse = JSON.parse(xhr.responseText);
                    console.error("Risposta di errore parsata:", errorResponse);
                } catch (e) {
                    console.error("Impossibile analizzare la risposta come JSON:", e);
                }
                
                showAlert("error", "Errore di connessione. Riprova più tardi v.");
                
                // Imposta valori di errore
                $("#userEmail").text("Errore di connessione");
                $("#userName").text("Errore di connessione");
                $("#accountId").text("Errore di connessione");
                $("#accountCreationDate").text("Errore di connessione");
            }
        });
    }
    
    $("#changeNameBtn").on("click", function() {
        $("#currentNameDisplay").hide();
        $("#changeNameForm").show();
        $("#newUsername").val($("#userName").text()).focus();
    });
  
    $("#saveNameBtn").on("click", function() {
        const newUsername = $("#newUsername").val();
        
        if (!newUsername) {
            showAlert("error", "Il nome utente è obbligatorio");
            return;
        }
        
        console.log("Invio richiesta cambio username:", newUsername);
        
        // Invia richiesta di cambio del nome utente
        $.ajax({
            url: Miaurl,
            type: "POST",
            data: {
                action: "change_username",
                new_username: newUsername
            },
            dataType: "json",
            success: function(response) {
                console.log("Risposta cambio username:", response);
                if (response.success) {
               
                    $("#userName").text(response.new_username);
                    
                
                    $("#changeNameForm").hide();
                    $("#currentNameDisplay").show();
                    
                    showAlert("success", response.message);
                } else {
                    showAlert("error", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Errore AJAX cambio username:", status, error);
                console.error("Response Text:", xhr.responseText);
                showAlert("error", "ma perchèèèèèèèèèèè");
            }
        });
    });
    
    // Gestione annullamento cambio nome utente
    $("#cancelNameBtn").on("click", function() {
        $("#changeNameForm").hide();
        $("#currentNameDisplay").show();
    });
    
    // Gestione apertura form per cambio password
    $("#changePasswordBtn").on("click", function() {
        $("#passwordSection").hide();
        $("#changePasswordForm").show();
        $("#currentPassword").val("").focus();
        $("#newPassword").val("");
        $("#confirmPassword").val("");
    });
    
    // Gestione invio form per cambio password
    $("#savePasswordBtn").on("click", function() {
        const currentPassword = $("#currentPassword").val();
        const newPassword = $("#newPassword").val();
        const confirmPassword = $("#confirmPassword").val();
        
        if (!currentPassword || !newPassword || !confirmPassword) {
            showAlert("error", "Tutti i campi sono obbligatori");
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showAlert("error", "Le nuove password non corrispondono");
            return;
        }
        
        console.log("Invio richiesta cambio password");
        
        // Invia richiesta di cambio password
        $.ajax({
            url: Miaurl,
            type: "POST",
            data: {
                action: "change_password",
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            },
            dataType: "json",
            success: function(response) {
                console.log("Risposta cambio password:", response);
                if (response.success) {
                    $("#changePasswordForm").hide();
                    $("#passwordSection").show();
                    
                    showAlert("success", response.message);
                } else {
                    showAlert("error", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Errore AJAX cambio password:", status, error);
                console.error("Response Text:", xhr.responseText);
                showAlert("error", "Errore di connessione. Riprova ffpiù tardi.");
            }
        });
    });
    
    // Gestione annullamento cambio password
    $("#cancelPasswordBtn").on("click", function() {
        $("#changePasswordForm").hide();
        $("#passwordSection").show();
    });
    
    // Gestione apertura form per disattivazione account
    $("#deactivateAccountBtn").on("click", function() {
        $("#confirmDeactivationModal").modal("show");
    });
    
    // Gestione invio form per disattivazione account
    $("#confirmDeactivateBtn").on("click", function() {
        const confirmPassword = $("#deactivatePassword").val();
        
        if (!confirmPassword) {
            showAlert("error", "La password è obbligatoria", "deactivate");
            return;
        }
        
        console.log("Invio richiesta disattivazione account");
        
        // Invia richiesta di disattivazione dekk account
        $.ajax({
            url: Miaurl,
            type: "POST",
            data: {
                action: "deactivate_account",
                confirm_password: confirmPassword
            },
            dataType: "json",
            success: function(response) {
                console.log("Risposta disattivazione account:", response);
                if (response.success) {
                    showAlert("success", response.message, "deactivate");
                    
                    setTimeout(function() {
                        window.location.href = response.redirect;
                    }, 2000);
                } else {
                    showAlert("error", response.message, "deactivate");
                }
            },
            error: function(xhr, status, error) {
                console.error("Errore AJAX disattivazione account:", status, error);
                console.error("Response Text:", xhr.responseText);
                showAlert("error", "Errore di connessione. Riprova più tardi no.", "deactivate");
            }
        });
    });
    
    // Funzione per mostrare gli avvisi
    function showAlert(type, message, context = "main") {
        console.log(`Mostrando avviso (${type}) in ${context}: ${message}`);
        
        let alertElement;
        if (context === "deactivate") {
            alertElement = $("#confirmDeactivationModal .alert-container");
        } else {
            alertElement = $("#alertContainer");
        }
        
        alertElement.empty();
        
        let alertClass = "";
        switch (type) {
            case "success":
                alertClass = "alert-success";
                break;
            case "error":
                alertClass = "alert-danger";
                break;
            default:
                alertClass = "alert-info";
        }
        
        const alertHtml = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>`;
        
        alertElement.html(alertHtml);
    
        if (type === "success") {
            setTimeout(function() {
                alertElement.find(".alert").alert("close");
            }, 3000);
        }
    }
}); 