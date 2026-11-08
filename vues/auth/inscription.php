<?php $erreurs = $erreurs ?? []; ?>
<div class="auth-page">
  <section class="auth-recit" aria-labelledby="recit-inscription">
    <div>
      <span class="badge-doux"><?= icone('etincelle', 15) ?> 2 heures offertes à l’inscription</span>
      <h1 id="recit-inscription">Votre savoir vaut des heures.</h1>
      <p>Un compte par adresse électronique. Les mêmes identifiants servent pour animer et pour apprendre : votre rôle dépend de chaque séance.</p>
    </div>
    <ul class="auth-liste">
      <li><?= icone('check', 17) ?><span><b>Commencez avec 2 heures.</b> De quoi réserver vos premières séances dès aujourd’hui.</span></li>
      <li><?= icone('check', 17) ?><span><b>Choisissez vos talents.</b> Python, dessin, anglais, méthode… ce que vous savez expliquer compte.</span></li>
      <li><?= icone('check', 17) ?><span><b>Gardez la main.</b> Vous acceptez ou refusez chaque demande de séance.</span></li>
    </ul>
    <p class="discret" style="color:var(--barre-douce);margin:0">Les données restent sur le serveur du campus. Aucun paiement, aucun abonnement.</p>
  </section>

  <section class="auth-carte" aria-labelledby="titre-inscription">
    <h1 id="titre-inscription">Créer mon compte</h1>
    <p class="sous-titre">Quelques informations, puis choisissez vos compétences.</p>
    <?= resume_erreurs($erreurs) ?>

    <form method="post">
      <?= csrf_champ() ?>
      <div class="ligne">
        <div class="champ">
          <label for="prenom">Prénom</label>
          <input id="prenom" type="text" name="prenom" value="<?= v($f, 'prenom') ?>" autocomplete="given-name"
                 minlength="2" maxlength="50" required<?= attrs_erreur($erreurs, 'prenom', 'prenom-erreur') ?>>
          <?= erreur_html($erreurs, 'prenom', 'prenom-erreur') ?>
        </div>
        <div class="champ">
          <label for="nom">Nom</label>
          <input id="nom" type="text" name="nom" value="<?= v($f, 'nom') ?>" autocomplete="family-name"
                 minlength="2" maxlength="50" required<?= attrs_erreur($erreurs, 'nom', 'nom-erreur') ?>>
          <?= erreur_html($erreurs, 'nom', 'nom-erreur') ?>
        </div>
      </div>
      <div class="champ">
        <label for="email">Adresse électronique</label>
        <input id="email" type="email" name="email" value="<?= v($f, 'email') ?>" autocomplete="username"
               maxlength="190" required<?= attrs_erreur($erreurs, 'email', 'email-erreur') ?>>
        <?= erreur_html($erreurs, 'email', 'email-erreur') ?>
      </div>
      <div class="champ">
        <label for="filiere">Filière</label>
        <input id="filiere" type="text" name="filiere" value="<?= v($f, 'filiere') ?>" list="filieres"
               placeholder="DUT Génie Informatique" minlength="2" maxlength="60"
               required<?= attrs_erreur($erreurs, 'filiere', 'filiere-erreur') ?>>
        <datalist id="filieres">
          <option>DUT Génie Informatique</option><option>DUT Techniques de Management</option>
          <option>DUT Génie Électrique</option><option>DUT Génie des Procédés</option>
          <option>DUT Techniques de Commercialisation</option>
        </datalist>
        <?= erreur_html($erreurs, 'filiere', 'filiere-erreur') ?>
      </div>
      <div class="ligne">
        <div class="champ">
          <label for="mdp">Mot de passe</label>
          <input id="mdp" type="password" name="mot_de_passe" autocomplete="new-password"
                 minlength="8" required data-mot-de-passe<?= attrs_erreur($erreurs, 'mot_de_passe', 'mdp-erreur', 'mdp-aide') ?>>
          <div class="aide" id="mdp-aide">8 caractères minimum, avec au moins une lettre et un chiffre.</div>
          <?= erreur_html($erreurs, 'mot_de_passe', 'mdp-erreur') ?>
        </div>
        <div class="champ">
          <label for="mdp2">Confirmation</label>
          <input id="mdp2" type="password" name="confirmation" autocomplete="new-password" minlength="8"
                 required data-meme-que="mdp" data-message-meme="Les deux mots de passe ne correspondent pas."
                 <?= attrs_erreur($erreurs, 'confirmation', 'mdp2-erreur') ?>>
          <?= erreur_html($erreurs, 'confirmation', 'mdp2-erreur') ?>
        </div>
      </div>
      <button class="bouton large" type="submit">Créer mon compte et recevoir 2 h</button>
    </form>
    <p class="auth-alt">Déjà inscrit ? <a href="<?= e(url('/connexion')) ?>">Connectez-vous</a>.</p>
  </section>
</div>
