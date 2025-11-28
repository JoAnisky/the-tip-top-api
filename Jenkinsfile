pipeline {
    agent any

    environment {
        APP_NAME = "the-tip-top-api"
        DOCKER_IMAGE = "joanisky/the-tip-top-api"
        DOCKER_TAG = "latest"
        DOCKER_TAG_BUILD = "${BUILD_NUMBER}"
        REGISTRY = "index.docker.io"
        KUBE_NAMESPACE = "the-tip-top-api"
        KUBE_DEPLOYMENT = "symfony-api"
    }

    stages {

        stage('Checkout') {
            steps {
                checkout scm
                script {
                    echo "Build: ${BUILD_NUMBER}"
                }
            }
        }

        stage('Build Docker Image') {
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
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG} \
                            -t ${DOCKER_IMAGE}:${DOCKER_TAG_BUILD} .
                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG}
                        docker push ${DOCKER_IMAGE}:${DOCKER_TAG_BUILD}
                    '''
                }
            }
        }

        stage('Deploy JWT Secrets') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig',      variable: 'KUBECONFIG_FILE'),
                        file(credentialsId: 'jwt-private-key', variable: 'JWT_PRIVATE_FILE'),
                        file(credentialsId: 'jwt-public-key',  variable: 'JWT_PUBLIC_FILE')
                    ]) {
                        sh '''
                            mkdir -p ~/.kube
                            cp $KUBECONFIG_FILE ~/.kube/config
                            chmod 600 ~/.kube/config

                            echo "Deleting old JWT secret (if exists)..."
                            kubectl delete secret jwt-keys -n ${KUBE_NAMESPACE} || true

                            echo "Creating new JWT secret from files..."
                            kubectl create secret generic jwt-keys \
                                --from-file=private.pem=$JWT_PRIVATE_FILE \
                                --from-file=public.pem=$JWT_PUBLIC_FILE \
                                -n ${KUBE_NAMESPACE}
                        '''
                    }
                }
            }
        }

        stage('Deploy to Kubernetes') {
            when {
                expression { env.GIT_BRANCH == 'origin/main' }
            }
            steps {
                script {
                    withCredentials([
                        file(credentialsId: 'kubeconfig', variable: 'KUBECONFIG_FILE')
                    ]) {
                        sh '''
                            mkdir -p ~/.kube
                            cp $KUBECONFIG_FILE ~/.kube/config
                            chmod 600 ~/.kube/config

                            echo "** Applying Kubernetes manifests **"
