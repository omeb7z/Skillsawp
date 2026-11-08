<?php
$erreurs = $erreurs ?? [];
$bio = (string) ($u['bio'] ?? '');
?>
<div class="entete">
  <div>
    <h1>Mon profil</h1>
    <p>Ce que vous savez transmettre et ce que vous voulez apprendre alimente vos suggestions.</p>
  </div>
  <a class="bouton second" href="<?= e(url('/profil/' . $u['id'])) ?>">Voir mon profil public <?= icone('fleche', 16) ?></a>
</div>

<?= resume_erreurs($erreurs) ?>

<form method="post">
  <?= csrf_champ() ?>
  <div class="cote">
    <div>
      <section class="panneau" aria-labelledby="titre-identite">
        <h2 id="titre-identite">Identité</h2>
        <div class="ligne">
          <div class="champ">
            <label for="prenom">Prénom</label>
            <input id="prenom" type="text" name="prenom" value="<?= e($u['prenom']) ?>" autocomplete="given-name"
                   minlength="2" maxlength="50" required<?= attrs_erreur($erreurs, 'prenom', 'prenom-erreur') ?>>
            <?= erreur_html($erreurs, 'prenom', 'prenom-erreur') ?>
          </div>
          <div class="champ">
            <label for="nom">Nom</label>
            <input id="nom" type="text" name="nom" value="<?= e($u['nom']) ?>" autocomplete="family-name"
                   minlength="2" maxlength="50" required<?= attrs_erreur($erreurs, 'nom', 'nom-erreur') ?>>
            <?= erreur_html($erreurs, 'nom', 'nom-erreur') ?>
          </div>
        </div>
        <div class="champ">
          <label for="email">Adresse électronique</label>
          <input id="email" type="email" value="<?= e($u['email']) ?>" disabled aria-describedby="email-aide">
          <div class="aide" id="email-aide">L’adresse identifie votre compte et ne peut pas être modifiée.</div>
        </div>
        <div class="champ">
          <label for="filiere">Filière</label>
          <input id="filiere" type="text" name="filiere" value="<?= e($u['filiere']) ?>" autocomplete="organization"
                 minlength="2" maxlength="60" required<?= attrs_erreur($erreurs, 'filiere', 'filiere-erreur') ?>>
          <?= erreur_html($erreurs, 'filiere', 'filiere-erreur') ?>
        </div>
        <div class="champ">
          <label for="bio">Présentation <span class="discret">(facultatif)</span></label>
          <textarea id="bio" name="bio" maxlength="400"
                    placeholder="Quelques mots sur vous, votre façon d’expliquer…"<?= attrs_erreur($erreurs, 'bio', 'bio-erreur', 'bio-aide') ?>><?= e($bio) ?></textarea>
          <div class="aide" id="bio-aide">400 caractères maximum. Elle apparaît sur votre profil public.</div>
          <?= erreur_html($erreurs, 'bio', 'bio-erreur') ?>
        </div>
      </section>

      <?php foreach ([['proposees', 'Je peux transmettre', 'Les compétences que vous pourriez enseigner.', $proposees],
                      ['recherchees', 'Je veux apprendre', 'Elles servent à vous suggérer des séances.', $recherchees]] as [$sens, $t, $aide, $coches]): ?>
        <section class="panneau" id="<?= $sens ?>" aria-labelledby="titre-<?= $sens ?>">
          <h2 id="titre-<?= $sens ?>"><?= e($t) ?></h2>
          <p class="discret"><?= e($aide) ?></p>
          <?php foreach ($groupes as $g): ?>
            <fieldset style="border:0;padding:0;margin:0 0 14px">
              <legend class="categorie" style="--cat:<?= e($g['couleur']) ?>;margin-bottom:6px"><?= e($g['nom']) ?></legend>
              <div class="choix">
                <?php foreach ($g['competences'] as $c): ?>
                  <label><input type="checkbox" name="<?= $sens ?>[]" value="<?= (int) $c['id'] ?>"
                    <?= in_array((int) $c['id'], $coches, true) ? 'checked' : '' ?>><span><?= e($c['nom']) ?></span></label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <div class="actions" style="margin-top:18px">
        <button class="bouton" type="submit">Enregistrer mon profil</button>
        <a class="bouton second" href="<?= e(url('/tableau-de-bord')) ?>">Retour à l’accueil</a>
      </div>
    </div>

    <aside>
      <section class="panneau" aria-labelledby="titre-apercu">
        <h3 id="titre-apercu">Votre profil en un coup d’œil</h3>
        <p class="discret">Plus votre profil est précis, plus les suggestions et les demandes sont pertinentes.</p>
        <div class="pile">
          <div class="badge-doux"><?= icone('offre', 16) ?> <?= count($proposees) ?> compétence(s) à transmettre</div>
          <div class="badge-doux"><?= icone('etincelle', 16) ?> <?= count($recherchees) ?> envie(s) d’apprendre</div>
        </div>
        <p style="margin:16px 0 0"><a href="<?= e(url('/profil/' . $u['id'])) ?>">Voir comment les autres vous voient <?= icone('fleche', 15) ?></a></p>
      </section>
      <section class="panneau douce">
        <h3>Un bon point de départ</h3>
        <p class="discret" style="margin:0">Choisissez une ou deux compétences que vous savez expliquer calmement. Vous pourrez toujours en ajouter plus tard.</p>
      </section>
    </aside>
  </div>
</form>

<div class="panneau" style="max-width:720px;margin-top:18px">
  <form method="post" action="<?= e(url('/profil/mot-de-passe')) ?>">
    <?= csrf_champ() ?>
    <h2>Changer de mot de passe</h2>
    <p class="discret">Votre session est renouvelée après la modification.</p>
    <div class="champ">
      <label for="actuel">Mot de passe actuel</label>
      <input id="actuel" type="password" name="actuel" autocomplete="current-password" required>
    </div>
    <div class="ligne">
      <div class="champ">
        <label for="nouveau">Nouveau mot de passe</label>
        <input id="nouveau" type="password" name="nouveau" autocomplete="new-password" minlength="8" required data-mot-de-passe>
        <div class="aide">8 caractères minimum, avec au moins une lettre et un chiffre.</div>
      </div>
      <div class="champ">
        <label for="conf">Confirmation</label>
        <input id="conf" type="password" name="confirmation" autocomplete="new-password" minlength="8"
               required data-meme-que="nouveau" data-message-meme="Les deux mots de passe ne correspondent pas.">
      </div>
    </div>
    <button class="bouton second" type="submit">Changer le mot de passe</button>
  </form>
</div>

  <div class="actions" style="margin-top:18px">
    <a class="bouton ghost" href="<?= e(url('/portefeuille')) ?>"><?= icone('portefeuille', 17) ?> Voir mon portefeuille</a>
  </div>
