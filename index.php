<?php
// ==========================================
// 1. PARTIE BACKEND : TRAITEMENT PHP & BDD
// ==========================================
require_once 'fonctions.php';

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $pdo = connectDatabase();
    $action = $_GET['action'];

    switch ($action) {

        
        // LISTER LES LIVRES
        case 'list':
            try {
                $stmt = $pdo->query("SELECT * FROM livres ORDER BY id DESC");
                $books = $stmt->fetchAll();
                echo json_encode($books);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        // AJOUTER OU MODIFIER UN LIVRE
        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Données invalides.']);
                exit;
            }

            $titre  = htmlspecialchars(trim($input['titre']), ENT_QUOTES, 'UTF-8');
            $auteur = htmlspecialchars(trim($input['auteur']), ENT_QUOTES, 'UTF-8');
            $annee  = !empty($input['annee']) ? (int)$input['annee'] : null;
            $isbn   = !empty($input['isbn']) ? htmlspecialchars(trim($input['isbn']), ENT_QUOTES, 'UTF-8') : null;
            $statut = ($input['statut'] === 'emprunte') ? 'emprunte' : 'disponible';
            $id     = !empty($input['id']) ? (int)$input['id'] : null;

            // Validation via fonction.php
            $validation = checkBookData($titre, $auteur, $annee);
            if ($validation !== true) {
                echo json_encode(['success' => false, 'message' => $validation]);
                exit;
            }

            try {
                if ($id) {
                    // Mode modification
                    $sql = "UPDATE livres SET titre = ?, auteur = ?, annee = ?, isbn = ?, statut = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$titre, $auteur, $annee, $isbn, $statut, $id]);
                } else {
                    // Mode ajout
                    $sql = "INSERT INTO livres (titre, auteur, annee, isbn, statut) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$titre, $auteur, $annee, $isbn, $statut]);
                }
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement en BDD.']);
            }
            exit;

        // SUPPRIMER UN LIVRE
        case 'delete':
            $id = (int)($_GET['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM livres WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur de suppression.']);
            }
            exit;

        // BASCULER LE STATUT RAPIDEMENT
        case 'toggle_status':
            $id = (int)($_GET['id'] ?? 0);
            try {
                // On récupère le statut actuel
                $stmt = $pdo->prepare("SELECT statut FROM livres WHERE id = ?");
                $stmt->execute([$id]);
                $book = $stmt->fetch();

                if ($book) {
                    $newStatus = ($book['statut'] === 'disponible') ? 'emprunte' : 'disponible';
                    $updateStmt = $pdo->prepare("UPDATE livres SET statut = ? WHERE id = ?");
                    $updateStmt->execute([$newStatus, $id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Livre introuvable.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du changement de statut.']);
            }
            exit;
    }
}
?>
<!-- 
==========================================
2. PARTIE FRONTEND : INTERFACE HTML / CSS
==========================================
-->
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion Bibliothèque Professionnelle</title>
<style>
  :root {
    --bg-main: #3b6d9e;
    --primary: #0f172a;
    --accent-green: #16a34a;
    --accent-blue: #2563eb;
    --accent-red: #dc2626;
    --border: #cbd5e1;
  }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg-main); margin: 0; padding-bottom: 40px; color: #334155; }
  header { background: var(--primary); color: white; padding: 20px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
  header h1 { margin: 0; font-size: 24px; font-weight: 600; }
  .container { max-width: 1200px; margin: auto; padding: 20px; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
  .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; text-align: center; }
  .card h3 { margin: 0 0 10px 0; color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
  .card p { margin: 0; font-size: 28px; font-weight: bold; color: var(--primary); }
  .actions-bar { display: flex; gap: 10px; margin-bottom: 20px; }
  .search { display: flex; gap: 12px; margin-bottom: 25px; flex-wrap: wrap; }
  .search input, .search select { padding: 12px; flex: 1; min-width: 180px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; background: white; }
  .search input:focus, .search select:focus { outline: 2px solid var(--accent-blue); }
  .books { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
  .book { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
  .book-info h3 { margin: 0 0 12px 0; color: var(--primary); font-size: 18px; line-height: 1.4; }
  .book-info p { margin: 6px 0; font-size: 14px; }
  .book-info b { color: #64748b; }
  .status-badge { inline-size: max-content; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
  .status-dispo { background: #dcfce7; color: #15803d; }
  .status-emp { background: #fee2e2; color: #b91c1c; }
  .book-actions { margin-top: 20px; display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid #f1f5f9; padding-top: 15px; }
  .btn { padding: 10px 16px; border: none; cursor: pointer; border-radius: 8px; font-weight: 600; font-size: 13px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; }
  .btn:hover { opacity: 0.9; transform: translateY(-1px); }
  .btn-add { background: var(--accent-green); color: white; font-size: 15px; }
  .btn-edit { background: #eff6ff; color: var(--accent-blue); border: 1px solid #bfdbfe; }
  .btn-del { background: #fef2f2; color: var(--accent-red); border: 1px solid #fecaca; }
  .btn-toggle { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; margin-inline-start: auto; }
  
  /* Modal Structuré */
  .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
  .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); animation: scaleUp 0.2s ease-out; }
  @keyframes scaleUp { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  .modal-content h2 { margin: 0 0 20px 0; font-size: 20px; color: var(--primary); }
  .form-group { margin-bottom: 16px; display: flex; flex-direction: column; }
  .form-group label { margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #475569; }
  .form-group input, .form-group select { padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; }
  .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
</style>
</head>
<body>

<header>
  <h1>Gestion Bibliothèque</h1>
</header>

<div class="container">
  <!-- Section Statistiques -->
  <div class="stats">
    <div class="card"><h3>Total Livres</h3><p id="total">0</p></div>
    <div class="card"><h3>Disponibles</h3><p id="dispo">0</p></div>
    <div class="card"><h3>Empruntés</h3><p id="emp">0</p></div>
  </div>

  <div class="actions-bar">
    <button class="btn btn-add" onclick="openModal()">+ Ajouter un livre</button>
  </div>

  <!-- Barre de Filtres et Recherche -->
  <div class="search">
    <input type="text" id="search" placeholder="Rechercher par titre ou auteur..." oninput="filterBooks()">
    <select id="status" onchange="filterBooks()">
      <option value="">Tous les statuts</option>
      <option value="disponible">Disponible</option>
      <option value="emprunte">Emprunté</option>
    </select>
    <select id="sort" onchange="filterBooks()">
      <option value="date">Plus récents en premier</option>
      <option value="titre">Par Titre (A-Z)</option>
    </select>
  </div>

  <!-- Conteneur de la Grille de Livres -->
  <div class="books" id="books"></div>
</div>

<!-- Fenêtre Modale (Ajout / Modification) -->
<div class="modal" id="bookModal">
  <div class="modal-content">
    <h2 id="modalTitle">Ajouter un Livre</h2>
    <form id="bookForm" onsubmit="saveBook(event)">
      <input type="hidden" id="bookId">
      
      <div class="form-group">
        <label for="titre">Titre *</label>
        <input type="text" id="titre" required autocomplete="off">
      </div>
      <div class="form-group">
        <label for="auteur">Auteur *</label>
        <input type="text" id="auteur" required autocomplete="off">
      </div>
      <div class="form-group">
        <label for="annee">Année de publication</label>
        <input type="number" id="annee" min="0" max="2100">
      </div>
      <div class="form-group">
        <label for="isbn">ISBN</label>
        <input type="text" id="isbn" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="formStatut">Statut initial</label>
        <select id="formStatut">
          <option value="disponible">Disponible</option>
          <option value="emprunte">Emprunté</option>
        </select>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn" style="background:#e2e8f0; color:#475569;" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn btn-add" id="submitBtn">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- 
==========================================
3. PARTIE FRONTEND : LOGIQUE JAVASCRIPT
==========================================
-->
<script>
let books = [];
let filtered = [];

// Chargement global des livres depuis l'API PHP
async function loadBooks(){
  try{
    const res = await fetch("?action=list");
    books = await res.json();
    filterBooks();
  }catch(e){
    console.error("Erreur critique de communication avec le serveur PHP.");
  }
}

function updateStats(){
  document.getElementById("total").innerText = books.length;
  document.getElementById("dispo").innerText = books.filter(b => b.statut === "disponible").length;
  document.getElementById("emp").innerText = books.filter(b => b.statut === "emprunte").length;
}

function filterBooks(){
  const s = document.getElementById("search").value.toLowerCase().trim();
  const st = document.getElementById("status").value;
  const sort = document.getElementById("sort").value;

  filtered = books.filter(b => {
    if(s && !(`${b.titre} ${b.auteur}`).toLowerCase().includes(s)) return false;
    if(st && b.statut !== st) return false;
    return true;
  });

  if(sort === "titre"){
    filtered.sort((a,b) => a.titre.localeCompare(b.titre));
  }else{
    // Tri par ID décroissant (plus récents en premier)
    filtered.sort((a,b) => b.id - a.id);
  }

  displayBooks();
  updateStats();
}

function displayBooks(){
  const container = document.getElementById("books");
  if(filtered.length === 0){
    container.innerHTML = `<p style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 40px; background: white; border-radius: 12px; border: 1px dashed var(--border);">Aucun livre ne correspond à vos critères.</p>`;
    return;
  }

  container.innerHTML = filtered.map(book => `
    <div class="book">
      <div class="book-info">
        <h3>${book.titre}</h3>
        <p><b>Auteur :</b> ${book.auteur}</p>
        <p><b>ISBN :</b> ${book.isbn || "-"}</p>
        <p><b>Année :</b> ${book.annee || "-"}</p>
        <p style="margin-top:12px;">
          <span class="status-badge ${book.statut === 'disponible' ? 'status-dispo' : 'status-emp'}">
            ${book.statut === 'disponible' ? 'Disponible' : 'Emprunté'}
          </span>
        </p>
      </div>
      <div class="book-actions">
        <button class="btn btn-edit" onclick="editBook(${book.id})">Modifier</button>
        <button class="btn btn-del" onclick="deleteBook(${book.id})">Supprimer</button>
        <button class="btn btn-toggle" onclick="toggleStatus(${book.id})" title="Changer le statut rapidement">⚡ Statut</button>
      </div>
    </div>
  `).join('');
}

async function deleteBook(id){
  if(!confirm("Êtes-vous sûr de vouloir définitivement supprimer ce livre ?")) return;
  const res = await fetch(`?action=delete&id=${id}`);
  const r = await res.json();
  if(r.success) loadBooks();
}

async function toggleStatus(id){
  const res = await fetch(`?action=toggle_status&id=${id}`);
  const r = await res.json();
  if(r.success) loadBooks();
}

/* LOGIQUE DE LA FENETRE MODALE */
function openModal() {
  document.getElementById("bookForm").reset();
  document.getElementById("bookId").value = "";
  document.getElementById("modalTitle").innerText = "Ajouter un Livre à la collection";
  document.getElementById("bookModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("bookModal").style.display = "none";
}

function editBook(id){
  const book = books.find(b => b.id == id);
  if(!book) return;

  document.getElementById("bookId").value = book.id;
  document.getElementById("titre").value = book.titre;
  document.getElementById("auteur").value = book.auteur;
  document.getElementById("annee").value = book.annee || "";
  document.getElementById("isbn").value = book.isbn || "";
  document.getElementById("formStatut").value = book.statut;

  document.getElementById("modalTitle").innerText = "Modifier les détails du Livre";
  document.getElementById("bookModal").style.display = "flex";
}

async function saveBook(e) {
  e.preventDefault();
  
  const data = {
    id: document.getElementById("bookId").value,
    titre: document.getElementById("titre").value,
    auteur: document.getElementById("auteur").value,
    annee: document.getElementById("annee").value,
    isbn: document.getElementById("isbn").value,
    statut: document.getElementById("formStatut").value
  };

  const res = await fetch("?action=save", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  });

  const r = await res.json();
  if(r.success) {
    closeModal();
    loadBooks();
  } else {
    alert(r.message || "Erreur lors de la sauvegarde.");
  }
}

// Lancement automatique au chargement initial
loadBooks();
</script>
</body>
</html>