<?php $erreurs = $erreurs ?? []; ?>
<div class="entete">
  <div>
    <h1>Mot de passe administrateur</h1>
    <p>Changez le mot de passe généré à l’installation. Il n’est jamais stocké en clair et la session est renouvelée après modification.</p>
  </div>
</div>

<div class="panneau" style="max-width:640px">
  <?= resume_erreurs($erreurs) ?>
  <form method="post">
    <?= csrf_champ() ?>
    <div class="champ">
      <label for="actuel">Mot de passe actuel</label>
      <input id="actuel" type="password" name="actuel" autocomplete="current-password"
             required<?= attrs_erreur($erreurs, 'actuel', 'actuel-erreur') ?>>
      <?= erreur_html($erreurs, 'actuel', 'actuel-erreur') ?>
    </div>
    <div class="ligne">
      <div class="champ">
        <label for="nouveau">Nouveau mot de passe</label>
        <input id="nouveau" type="password" name="nouveau" autocomplete="new-password" minlength="8"
               required data-mot-de-passe<?= attrs_erreur($erreurs, 'nouveau', 'nouveau-erreur', 'nouveau-aide') ?>>
        <div class="aide" id="nouveau-aide">8 caractères minimum, avec au moins une lettre et un chiffre.</div>
        <?= erreur_html($erreurs, 'nouveau', 'nouveau-erreur') ?>
      </div>
      <div class="champ">
        <label for="confirmation">Confirmation</label>
        <input id="confirmation" type="password" name="confirmation" autocomplete="new-password" minlength="8"
               required data-meme-que="nouveau" data-message-meme="Les deux mots de passe ne correspondent pas."
               <?= attrs_erreur($erreurs, 'confirmation', 'confirmation-erreur') ?>>
        <?= erreur_html($erreurs, 'confirmation', 'confirmation-erreur') ?>
      </div>
    </div>
    <div class="actions">
      <button class="bouton" type="submit">Changer le mot de passe</button>
      <a class="bouton second" href="<?= e(url('/admin')) ?>">Annuler</a>
    </div>
  </form>
</div>
