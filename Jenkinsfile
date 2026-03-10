pipeline {
    agent none

    environment {
        APP_NAME = "the-tip-top-api"
        DOCKER_IMAGE = "joanisky/the-tip-top-api"
        DOCKER_TAG = "${BUILD_NUMBER}"
        KUBE_NAMESPACE = "the-tip-top-api"
        KUBE_DEPLOYMENT = "symfony-api"
    }

    stages {
        stage('Checkout') {
            agent any
            steps {
                checkout scm
                script {
                    env.GIT_BRANCH_NAME = env.GIT_BRANCH ?: scm.branches[0].name
                    echo "Build: ${BUILD_NUMBER} — Branche: ${env.GIT_BRANCH_NAME}"
                }
            }
        }
        stage('Tests') {
            agent {
                kubernetes {
                    yamlFile 'k8s/jenkins/php-test-pod.yaml'
                    defaultContainer 'php'
                }
            }
            steps {
                container('php') {
                    sh '''
                        # Attendre que MariaDB soit prête via PHP (pas besoin du client mariadb)
                        echo "Attente de MariaDB..."
                        until php -r "
                            try {
                                new PDO('mysql:host=localhost;port=3306;dbname=the_tip_top_testdb_test', 'test', 'test');
                                exit(0);
                            } catch (Exception \$e) {
                                exit(1);
                            }
                        "; do
                            echo "MariaDB pas encore prête, nouvelle tentative dans 3s..."
                            sleep 3
                        done
                        echo "MariaDB prête."

                        # --- Installer les dépendances avec les packages de test ---
                        composer install --prefer-dist --no-progress --no-interaction

                        # --- Préparer le fichier .env.test pour le container ---
                        # DATABASE_URL est déjà injecté via les env vars du pod
                        echo "APP_ENV=test" > .env.test.local
                        echo "DATABASE_URL=${DATABASE_URL}" >> .env.test.local

                        # --- Créer le schéma de la base de test ---
                        php bin/console doctrine:schema:create --env=test --no-interaction

                        # --- Lancer tous les tests avec rapport JUnit ---
                        mkdir -p test-results/phpunit
                        APP_ENV=test ./vendor/bin/phpunit \
                            --log-junit test-results/phpunit/junit.xml \
                            --testdox
                    '''
                }
            }
            post {
                always {
                    stash includes: 'test-results/phpunit/**', name: 'phpunit-reports', allowEmpty: true
                }
            }
        }
        stage('Publish Reports') {
            agent any
            options {
                skipDefaultCheckout true
            }
            steps {
                unstash 'phpunit-reports'
                junit allowEmptyResults: true, testResults: 'test-results/phpunit/junit.xml'
            }
            post {
                always {
                    cleanWs()
                }
            }
        }
        stage('Build & Push Docker Image') {
            agent any
            steps {
                withCredentials([
                    usernamePassword(credentialsId: 'jenkins-dockerhub', usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASS')
                ]) {
                    sh '''
                        docker login -u $DOCKER_USER -p $DOCKER_PASS

                        docker build \
                            -f .docker/Dockerfile \
                            --target prod \
                            --build-arg APP_ENV=prod \
                            --build-arg APP_SECRET=dummysecret \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG} .

                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG}
                    '''
                }
            }
        }
        stage('Deploy JWT Secrets') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig',      variable: 'KUBECONFIG_FILE'),
                        file(credentialsId: 'jwt-private-key', variable: 'JWT_PRIVATE_FILE'),
                        file(credentialsId: 'jwt-public-key',  variable: 'JWT_PUBLIC_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_PATH

                            echo "Ensuring JWT secrets are up to date..."
                            kubectl delete secret jwt-keys -n ${KUBE_NAMESPACE} || true
                            kubectl create secret generic jwt-keys \
                                --from-file=private.pem=$JWT_PRIVATE_FILE \
                                --from-file=public.pem=$JWT_PUBLIC_FILE \
                                -n ${KUBE_NAMESPACE}
                        '''
                    }
                }
            }
        }
        stage('Deploy OAuth Secrets') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                        string(credentialsId: 'GOOGLE_CLIENT_ID', variable: 'GOOGLE_ID'),
                        string(credentialsId: 'GOOGLE_CLIENT_SECRET', variable: 'GOOGLE_SECRET'),
                        string(credentialsId: 'FACEBOOK_CLIENT_ID', variable: 'FACEBOOK_ID'),
                        string(credentialsId: 'FACEBOOK_CLIENT_SECRET', variable: 'FACEBOOK_SECRET')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_FILE

                            echo "Deploying OAuth secrets..."
                            kubectl create secret generic oauth-secrets \
                                --from-literal=GOOGLE_CLIENT_ID=${GOOGLE_ID} \
                                --from-literal=GOOGLE_CLIENT_SECRET=${GOOGLE_SECRET} \
                                --from-literal=FACEBOOK_CLIENT_ID=${FACEBOOK_ID} \
                                --from-literal=FACEBOOK_CLIENT_SECRET=${FACEBOOK_SECRET} \
                                --from-literal=TRUSTED_PROXIES='10.0.0.0/8,172.16.0.0/12,192.168.0.0/16' \
                                -n ${KUBE_NAMESPACE} \
                                --dry-run=client -o yaml | kubectl apply -f -
                        '''
                    }
                }
            }
        }
        stage('Deploy Mysqld Exporter Secret') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE'),
                    string(credentialsId: 'MYSQLD_EXPORTER_PASSWORD', variable: 'EXPORTER_PASS')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE
                        kubectl create secret generic mysqld-exporter-secret \
                            --from-literal=DATA_SOURCE_NAME="exporter:$EXPORTER_PASS@tcp(mariadb:3306)/" \
                            -n the-tip-top-api \
                            --dry-run=client -o yaml | kubectl apply -f -
                        kubectl rollout restart deployment/mysqld-exporter -n the-tip-top-api
                    '''
                }
            }
        }
        stage('Deploy Backup Secrets') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig',     variable: 'KUBECONFIG_FILE'),
                    string(credentialsId: 'RESTIC_PASSWORD', variable: 'RESTIC_PASS'),
                    file(credentialsId: 'rclone-config',  variable: 'RCLONE_CONF')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        echo "Deploying Restic secret..."
                        kubectl create secret generic restic-secret \
                            --from-literal=RESTIC_PASSWORD=${RESTIC_PASS} \
                            -n the-tip-top-api \
                            --dry-run=client -o yaml | kubectl apply -f -

                        echo "Deploying rclone config secret..."
                        kubectl create secret generic rclone-config \
                            --from-file=rclone.conf=${RCLONE_CONF} \
                            -n the-tip-top-api \
                            --dry-run=client -o yaml | kubectl apply -f -
                    '''
                }
            }
        }
        stage('Deploy Backup CronJob') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE
                        kubectl apply -k k8s/backup/
                    '''
                }
            }
        }
        stage('Deploy PodMonitors') {
            agent any
            when { expression { env.GIT_BRANCH == 'origin/main' } }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE
                        kubectl apply -k k8s/monitoring/
                    '''
                }
            }
        }
        stage('Deploy to Kubernetes') {
            agent any
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_FILE

                            echo "Application des manifests Kubernetes..."
                            kubectl apply -k k8s/ -n ${KUBE_NAMESPACE}

                            echo "Mise à jour de l'image vers: ${DOCKER_TAG}"
                            kubectl set image deployment/${KUBE_DEPLOYMENT} \
                                app=${DOCKER_IMAGE}:${DOCKER_TAG} \
                                -n ${KUBE_NAMESPACE}

                            echo "Attente du rollout..."
                            kubectl rollout status deployment/${KUBE_DEPLOYMENT} \
                                -n ${KUBE_NAMESPACE} \
                                --timeout=300s
                        '''
                    }
                }
            }
        }
        stage('Database Migrations') {
            agent any
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                    ]) {
                        sh '''
                            export KUBECONFIG=$KUBECONFIG_PATH

                            echo "Mise à jour de la base de données ..."

                            # Crée la base si elle n'existe pas
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:database:create --if-not-exists --env=prod || true

                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console doctrine:schema:update --force --env=prod || true

                            # Forcer la suppression du cache avant cache:clear
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                rm -rf /var/www/html/var/cache/prod || true

                            # Cache clear
                            kubectl exec deployment/${KUBE_DEPLOYMENT} -n ${KUBE_NAMESPACE} -- \
                                php bin/console cache:clear --env=prod

                            echo "Redémarrage de phpMyAdmin"
                            kubectl rollout restart deployment/phpmyadmin -n ${KUBE_NAMESPACE} || true
                        '''
                    }
                }
            }
        }
        stage('Verify Deployment') {
            agent any
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                withCredentials([
                    file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                ]) {
                    sh '''
                        export KUBECONFIG=$KUBECONFIG_FILE

                        echo "État du déploiement :"
                        echo ""

                        echo "=== Pods ==="
                        kubectl get pods -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Services ==="
                        kubectl get svc -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Ingress ==="
                        kubectl get ingress -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== Secrets ==="
                        kubectl get secrets -n ${KUBE_NAMESPACE}
                        echo ""

                        echo "=== ConfigMaps ==="
                        kubectl get configmaps -n ${KUBE_NAMESPACE}
                    '''
                }
            }
        }
        stage('Health Check') {
            agent any
            steps {
                script {
                    timeout(time: 2, unit: 'MINUTES') {
                        waitUntil {
                            script {
                                def response = sh(
                                    // Teste la route /api (doc Swagger)
                                    script: 'curl -s -o /dev/null -w "%{http_code}" https://api.the-tip-top.jonathanlore.fr/api',
                                    returnStdout: true
                                ).trim()

                                if (response == '200') {
                                    echo "✅ API accessible"
                                    return true  // Stop le wait
                                } else {
                                    echo "⏳ En attente..."
                                    sleep 5
                                    return false  // Continue le wait
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    post {
        always {
            // Supprime les images locales pour ne pas saturer le disque du VPS
            node('built-in') {
                sh "docker rmi ${DOCKER_IMAGE}:${DOCKER_TAG} || true"
            }
        }
        success {
            echo "✅ ${APP_NAME} déployé avec succès !"
            echo "🌐 API : https://api.the-tip-top.jonathanlore.fr/api"
        }
        failure {
            echo "❌ Pipeline échoué"
        }
    }
}
